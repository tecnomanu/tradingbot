<?php

namespace App\Services\Agent;

use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\Bot;
use Illuminate\Support\Facades\Http;
use App\Support\BotLog as Log;

class AgentOrchestrator
{
    private const MAX_ITERATIONS = 4;

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

            $result = $this->runAgentLoop($conversation, $bot, $messages);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $conversation->update([
                'status' => 'completed',
                'summary' => $result['summary'],
                'analysis' => $result['analysis'] ?? null,
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
        $systemPrompt = $bot->ai_system_prompt ?: $this->defaultSystemPrompt();
        $userPrompt = $this->interpolateUserPrompt(
            $bot->ai_user_prompt ?: static::defaultUserPrompt(),
            $bot,
        );

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
    }

    public static function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
Grid trading bot advisor. Review bot state, analyze market, take action if needed.

Rules:
- Call get_bot_status + get_market_data first. Only call other tools if something looks off.
- Be conservative: act only with clear reason. Destructive actions need strong justification.
- Do NOT set SL/TP if already set at the same price. Only call set_stop_loss/set_take_profit when changing to a NEW value.
- Set/adjust SL/TP if: price near grid edge, RSI extreme (>70/<30), or negative unrealized PNL.
- If price outside grid range: critical, consider stopping.
- Call done when finished. In "analysis": write a human-readable market assessment in Spanish (2-3 sentences max explaining what you see and why you acted or not). In "summary": 1 short sentence. All prices USDT.
PROMPT;
    }

    public static function defaultUserPrompt(): string
    {
        return "Review Bot #{bot_id} ({symbol}) at {now} UTC. " .
               "Call get_bot_status and get_market_data. Only call other tools if something looks abnormal. " .
               "Then call done with a brief summary.";
    }

    private function interpolateUserPrompt(string $template, Bot $bot): string
    {
        $now = now()->format('M j H:i');
        return str_replace(
            ['{bot_id}', '{symbol}', '{now}'],
            [$bot->id, $bot->symbol, $now],
            $template,
        );
    }

    private function runAgentLoop(AiConversation $conversation, Bot $bot, array &$messages): array
    {
        $output = ['summary' => 'Agent did not complete analysis', 'analysis' => null];

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

            AiConversationMessage::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantMessage['content'] ?? null,
                'tool_calls' => $assistantMessage['tool_calls'] ?? null,
                'tokens' => $tokens,
            ]);

            if (!empty($assistantMessage['tool_calls'])) {
                $toolCallResults = $this->executeToolCalls($conversation, $bot, $assistantMessage['tool_calls']);

                foreach ($toolCallResults as $result) {
                    if ($result['tool_name'] === 'done') {
                        $args = $result['tool_args'] ?? [];
                        $output['summary'] = $args['summary'] ?? 'Analysis complete';
                        $output['analysis'] = $args['analysis'] ?? null;
                        return $output;
                    }
                }

                foreach ($toolCallResults as $result) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $result['tool_call_id'],
                        'name' => $result['tool_name'],
                        'content' => json_encode($result['result']),
                    ];
                }
            } else {
                if (!empty($assistantMessage['content'])) {
                    $output['summary'] = $assistantMessage['content'];
                }
                break;
            }
        }

        return $output;
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
                'tool_args' => $toolArgs,
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
                'temperature' => 0.1,
                'max_tokens' => 512,
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

            // Strip thinking tags from assistant content
            $msg = $choice['message'];
            if (!empty($msg['content'])) {
                $msg['content'] = trim(preg_replace('/<think>.*?(<\/think>|$)/s', '', $msg['content']));
            }

            return [
                'message' => $msg,
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
