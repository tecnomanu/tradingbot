<?php

namespace App\Services\Agent;

use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\Bot;
use Illuminate\Support\Facades\Http;
use App\Support\BotLog as Log;

class AgentOrchestrator
{
    private const MAX_ITERATIONS = 8;

    private string $apiUrl;
    private string $model;
    private string $apiKey;
    private int $totalTokens = 0;
    private int $totalToolCalls = 0;

    public function __construct(
        private AgentToolkit $toolkit,
    ) {
        $this->apiUrl = config('services.ai.url') ?: 'https://api.groq.com/openai/v1/chat/completions';
        $this->model = config('services.ai.model') ?: 'qwen/qwen3-32b';
        $this->apiKey = config('services.ai.key') ?: '';
    }

    public function consult(Bot $bot, string $trigger = 'scheduled'): AiConversation
    {
        $startTime = microtime(true);

        $conversation = AiConversation::create([
            'bot_id' => $bot->id,
            'status' => 'running',
            'trigger' => $trigger,
            'model' => $this->model,
            'started_at' => now(),
        ]);

        $this->toolkit->setConversationId($conversation->id);

        try {
            $messages = $this->buildInitialMessages($bot);
            $this->storeMessages($conversation, $messages);

            $summary = $this->runAgentLoop($conversation, $bot, $messages);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $conversation->update([
                'status' => 'completed',
                'summary' => $summary,
                'total_tokens' => $this->totalTokens,
                'total_tool_calls' => $this->totalToolCalls,
                'total_messages' => $conversation->messages()->count(),
                'duration_ms' => $durationMs,
                'actions_taken' => $conversation->actionLogs()->pluck('action')->toArray(),
                'ended_at' => now(),
            ]);

            Log::info('AgentOrchestrator: consultation complete', [
                'conversation_id' => $conversation->id,
                'bot_id' => $bot->id,
                'tokens' => $this->totalTokens,
                'tool_calls' => $this->totalToolCalls,
                'duration_ms' => $durationMs,
            ]);
        } catch (\Exception $e) {
            Log::error('AgentOrchestrator: consultation failed', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            $conversation->update([
                'status' => 'failed',
                'summary' => 'Error: ' . $e->getMessage(),
                'total_tokens' => $this->totalTokens,
                'ended_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $startTime) * 1000),
            ]);
        }

        return $conversation;
    }

    private function buildInitialMessages(Bot $bot): array
    {
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($bot);

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert crypto grid trading advisor acting as a human supervisor for an automated trading bot. You are called periodically to review the bot's state, analyze market conditions, and take corrective actions if needed.

## YOUR ROLE
- You simulate an experienced human trader checking on the bot
- You must gather information FIRST, analyze it, then decide on actions
- You have access to tools that let you inspect AND modify the bot's configuration
- Be conservative: only take action when there's a clear reason
- Destructive actions (stop_bot, cancel_all_orders, close_position) require strong justification

## WORKFLOW
1. ALWAYS start by calling `get_bot_status` and `get_market_data` to understand the current situation
2. If needed, call `get_open_orders`, `get_filled_orders`, or `get_binance_position` for deeper analysis
3. Evaluate if the grid range is still appropriate for current market conditions
4. Check if stop-loss and take-profit are properly set
5. Take corrective actions if needed (set SL/TP, etc.)
6. ALWAYS finish by calling `done` with a comprehensive summary

## DECISION CRITERIA
- If price is near grid boundary (within 1-2 grid steps): consider setting or tightening SL/TP
- If RSI > 70 and price near upper grid: consider setting TP if not set
- If RSI < 30 and price near lower grid: consider tightening SL
- If unrealized PNL is very negative: evaluate whether to close position
- If price has moved outside grid range: this is critical, consider stopping
- If everything looks normal: confirm and set/update SL/TP as safety nets

## IMPORTANT
- You MUST call at least `get_bot_status` and `get_market_data` before making any decisions
- You MUST call `done` when finished to provide your summary
- Never take destructive actions without checking the full picture first
- All prices are in USDT
PROMPT;
    }

    private function buildUserPrompt(Bot $bot): string
    {
        $now = now()->toDateTimeString();
        return "It is {$now} UTC. You are reviewing Bot #{$bot->id} ({$bot->symbol}). " .
               "Please perform your routine check: gather data, analyze conditions, take any necessary actions, " .
               "and provide your assessment. Start by checking the bot status and market data.";
    }

    private function runAgentLoop(AiConversation $conversation, Bot $bot, array &$messages): string
    {
        $summary = 'Agent did not complete analysis';

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            $response = $this->callLlm($messages);

            if (!$response) {
                Log::warning('AgentOrchestrator: LLM returned null', ['iteration' => $i]);
                break;
            }

            $assistantMessage = $response['message'];
            $tokens = $response['tokens'] ?? 0;
            $this->totalTokens += $tokens;

            $messages[] = $assistantMessage;

            // Store assistant message
            AiConversationMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantMessage['content'] ?? null,
                'tool_calls' => $assistantMessage['tool_calls'] ?? null,
                'tokens' => $tokens,
            ]);

            // Check if the assistant wants to call tools
            if (!empty($assistantMessage['tool_calls'])) {
                $toolCallResults = $this->executeToolCalls($conversation, $bot, $assistantMessage['tool_calls']);

                // Check if "done" was called
                foreach ($toolCallResults as $result) {
                    if ($result['tool_name'] === 'done') {
                        $summary = $result['result']['summary'] ?? 'Analysis complete';
                        return $summary;
                    }
                }

                // Add tool results to messages for the next LLM call
                foreach ($toolCallResults as $result) {
                    $toolMessage = [
                        'role' => 'tool',
                        'tool_call_id' => $result['tool_call_id'],
                        'name' => $result['tool_name'],
                        'content' => json_encode($result['result']),
                    ];
                    $messages[] = $toolMessage;
                }
            } else {
                // No tool calls - assistant provided a text response
                // This shouldn't happen in a well-behaved agent, but handle gracefully
                if (!empty($assistantMessage['content'])) {
                    $summary = $assistantMessage['content'];
                }
                break;
            }
        }

        return $summary;
    }

    private function executeToolCalls(AiConversation $conversation, Bot $bot, array $toolCalls): array
    {
        $results = [];

        foreach ($toolCalls as $tc) {
            $toolName = $tc['function']['name'] ?? '';
            $toolArgs = json_decode($tc['function']['arguments'] ?? '{}', true) ?: [];
            $toolCallId = $tc['id'] ?? uniqid('tc_');

            $this->totalToolCalls++;

            Log::info('AgentOrchestrator: executing tool', [
                'tool' => $toolName,
                'args' => $toolArgs,
                'conversation_id' => $conversation->id,
            ]);

            $result = $this->toolkit->executeTool($toolName, $toolArgs, $bot);

            // Store tool message
            AiConversationMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'tool',
                'tool_call_id' => $toolCallId,
                'tool_name' => $toolName,
                'tool_args' => $toolArgs,
                'tool_result' => $result,
            ]);

            $results[] = [
                'tool_call_id' => $toolCallId,
                'tool_name' => $toolName,
                'result' => $result,
            ];
        }

        return $results;
    }

    private function callLlm(array $messages): ?array
    {
        try {
            // Build the API messages (clean format for OpenAI-compatible API)
            $apiMessages = $this->buildApiMessages($messages);

            $payload = [
                'model' => $this->model,
                'messages' => $apiMessages,
                'tools' => $this->toolkit->getToolDefinitions(),
                'tool_choice' => 'auto',
                'temperature' => 0.2,
                'max_tokens' => 1024,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post($this->apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('AgentOrchestrator: LLM API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return null;
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (!$choice) {
                return null;
            }

            return [
                'message' => $choice['message'],
                'tokens' => $data['usage']['total_tokens'] ?? 0,
                'finish_reason' => $choice['finish_reason'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('AgentOrchestrator: callLlm exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildApiMessages(array $messages): array
    {
        $apiMessages = [];

        foreach ($messages as $msg) {
            $apiMsg = ['role' => $msg['role']];

            if (isset($msg['content'])) {
                $apiMsg['content'] = $msg['content'];
            }

            if (isset($msg['tool_calls'])) {
                $apiMsg['tool_calls'] = $msg['tool_calls'];
                if (!isset($apiMsg['content'])) {
                    $apiMsg['content'] = null;
                }
            }

            if (isset($msg['tool_call_id'])) {
                $apiMsg['tool_call_id'] = $msg['tool_call_id'];
            }

            if (isset($msg['name'])) {
                $apiMsg['name'] = $msg['name'];
            }

            $apiMessages[] = $apiMsg;
        }

        return $apiMessages;
    }

    private function storeMessages(AiConversation $conversation, array $messages): void
    {
        foreach ($messages as $msg) {
            AiConversationMessage::create([
                'conversation_id' => $conversation->id,
                'role' => $msg['role'],
                'content' => $msg['content'] ?? null,
            ]);
        }
    }
}
