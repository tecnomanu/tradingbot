<?php

namespace App\Services\Agent;

use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\Bot;
use Illuminate\Support\Facades\Http;
use App\Support\BotLog as Log;

class AgentOrchestrator
{
    private const MAX_ITERATIONS = 6;

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

    public function consult(Bot $bot, string $trigger = 'scheduled', ?string $alertContext = null): AiConversation
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
        $this->toolkit->setTrigger($trigger);

        try {
            $messages = $this->buildInitialMessages($bot, $trigger, $alertContext);
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

            $this->sendTelegramNotification($bot, $conversation);
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

    private function buildInitialMessages(Bot $bot, string $trigger = 'scheduled', ?string $alertContext = null): array
    {
        $personality = $bot->ai_system_prompt ?: static::defaultPersonality();
        $systemPrompt = $personality . "\n\n" . static::operationalPrompt();

        if ($trigger === 'scheduled') {
            $systemPrompt .= "\n\n" . static::scheduledModeConstraints();
        }

        $userPrompt = $this->interpolateUserPrompt(
            $bot->ai_user_prompt ?: static::defaultUserPrompt(),
            $bot,
        );

        // Prepend alert context so the agent immediately knows why it was called
        if ($alertContext) {
            $userPrompt = "⚠ ALERT — {$alertContext}\n\n" . $userPrompt;
        }

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ];
    }

    /**
     * Editable personality/strategy section. Stored in bot.ai_system_prompt.
     */
    public static function defaultPersonality(): string
    {
        return <<<'PROMPT'
Expert crypto grid trading supervisor. Moderate style: act only on clear signals, prefer stability, tolerate normal fluctuations. Intervene when RSI extreme or price near grid edges. When in doubt, observe.
PROMPT;
    }

    /**
     * Fixed operational instructions. NOT editable by users.
     * Contains workflow, tool usage rules, decision framework, and output format.
     */
    public static function operationalPrompt(): string
    {
        return <<<'PROMPT'
## WORKFLOW
1. Call get_bot_status + get_market_data first.
2. If bot is STOPPED → report only, NO action tools allowed.
3. Calculate: grid_position% = (price - lower) / (upper - lower) × 100
4. Apply ACTION GATE. If no condition met → call done() with status report.
5. Execute justified actions, then call done() ALONE (never batch with other tools).

## ACTION GATE — only act if one is true:
- grid_position% > 85 or < 15 | price outside grid | RSI > 75 or < 25
- Unrealized loss > 2% of investment | no SL set with open position
- open_orders = 0 on an active bot → CRITICAL: use adjust_grid to recenter the grid around current price (same range width). A grid bot with 0 open orders is not trading.
If none → do NOT call any action tool.

## RULES
- SL/TP: only change to a NEW value. SL above liquidation price. TP at Bollinger/resistance.
- Grid: prefer adjust_grid over stop_bot. Recenter when grid_position% > 85 or < 15.
- stop_bot: LAST RESORT. Must first try adjust_grid. Explain with specific numbers why stopping was necessary.
- Position: unrealized loss >2% + bearish → consider close_position + stop_bot.

## OUTPUT (done tool)
- analysis: 3-5 sentences in Spanish. Include price, grid_position% calculation, RSI, MACD, Bollinger. Explain actions or why none taken. Must be coherent with actions (don't say "no intervention" if you acted).
- summary: 1 sentence in Spanish.
PROMPT;
    }

    /**
     * Extra constraints injected only in scheduled (auto) consultations.
     * Prevents the self-defeating loop: agent stops bot → agent stops running → bot stuck forever.
     */
    public static function scheduledModeConstraints(): string
    {
        return <<<'PROMPT'
## SCHEDULED MODE — CRITICAL CONSTRAINT
stop_bot is DISABLED. Calling it will be blocked and will NOT stop the bot.
Reason: if the bot stops, this agent stops running (it only monitors active bots), creating a permanent outage with no automatic recovery.

If the situation is critical (large unrealized loss, extreme price move):
1. call close_position → reduces open exposure immediately
2. call adjust_grid → recenter grid around current price to adapt to new conditions
3. tighten stop_loss → set a protective SL close to current price
This protects capital WITHOUT stopping the bot. The agent will continue monitoring.

Only a manual consultation (user-triggered) can stop the bot.
PROMPT;
    }

    public static function defaultUserPrompt(): string
    {
        return "Check Bot #{bot_id} ({symbol}) — {now} UTC. Call get_bot_status + get_market_data, analyze, act if needed, finish with done().";
    }

    /**
     * Personality presets available from the admin panel.
     * @return array<string, array{label: string, prompt: string}>
     */
    public static function personalityPresets(): array
    {
        return [
            'conservative' => [
                'label' => 'Conservador',
                'prompt' => 'Cautious grid trading supervisor. Capital preservation first. Act only with overwhelming multi-indicator evidence. Tight SL, passive grid. Only adjust_grid in extreme prolonged misalignment.',
            ],
            'moderate' => [
                'label' => 'Moderado',
                'prompt' => static::defaultPersonality(),
            ],
            'aggressive' => [
                'label' => 'Agresivo',
                'prompt' => 'Aggressive grid trading supervisor. Maximize profit actively. Adjust grid when position% >85 or <15, recenter around price following trend. Tight SL, wide TP. Bullish (RSI>60+MACD+) → shift up. Bearish → narrow grid, tighten protections. Neutral zone (15-85% + RSI 40-60) → report only.',
            ],
        ];
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
            Log::info("AgentOrchestrator: iteration {$i}, messages=" . count($messages));
            $response = $this->callLlm($messages);

            if (!$response) {
                Log::warning("AgentOrchestrator: LLM returned null at iteration {$i}");
                break;
            }

            Log::info("AgentOrchestrator: iteration {$i} got response", [
                'finish_reason' => $response['finish_reason'] ?? 'unknown',
                'has_tool_calls' => !empty($response['message']['tool_calls']),
                'has_content' => !empty($response['message']['content']),
            ]);

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
                $allCalls = $assistantMessage['tool_calls'];
                $hasActions = collect($allCalls)->contains(fn($tc) =>
                    !in_array($tc['function']['name'] ?? '', ['done', 'get_bot_status', 'get_market_data', 'get_open_orders', 'get_filled_orders', 'get_binance_position'])
                );
                $hasDone = collect($allCalls)->contains(fn($tc) => ($tc['function']['name'] ?? '') === 'done');

                $doneDeferred = ($hasActions && $hasDone);
                $callsToExecute = $doneDeferred
                    ? array_values(array_filter($allCalls, fn($tc) => ($tc['function']['name'] ?? '') !== 'done'))
                    : $allCalls;

                $toolCallResults = $this->executeToolCalls($conversation, $bot, $callsToExecute);

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

                if ($doneDeferred) {
                    $doneCall = collect($allCalls)->first(fn($tc) => ($tc['function']['name'] ?? '') === 'done');
                    if ($doneCall) {
                        $doneArgs = json_decode($doneCall['function']['arguments'] ?? '{}', true) ?: [];
                        $deferredAnalysis = $doneArgs['analysis'] ?? null;
                        $deferredSummary = $doneArgs['summary'] ?? null;

                        $messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $doneCall['id'] ?? uniqid('tc_'),
                            'name' => 'done',
                            'content' => json_encode(['deferred' => true, 'reason' => 'Actions were executed. Review results above, then call done again with an analysis that accurately reflects the actions you took.']),
                        ];
                    }
                }
            } else {
                // Model returned text instead of tool calls.
                // Treat as final response if it looks like an analysis.
                if (!empty($assistantMessage['content'])) {
                    $content = $assistantMessage['content'];
                    $output['summary'] = mb_substr($content, 0, 200);
                    $output['analysis'] = $content;
                }
                break;
            }
        }

        if ($output['analysis'] === null) {
            // Try last assistant text first
            $lastAssistant = collect($messages)
                ->filter(fn($m) => ($m['role'] ?? '') === 'assistant' && !empty($m['content']))
                ->last();
            if ($lastAssistant) {
                $output['analysis'] = $lastAssistant['content'];
                $output['summary'] = mb_substr($lastAssistant['content'], 0, 200);
            }
            // Fall back to the deferred done() analysis if available
            elseif (!empty($deferredAnalysis ?? null)) {
                $output['analysis'] = $deferredAnalysis;
                $output['summary'] = $deferredSummary ?? mb_substr($deferredAnalysis, 0, 200);
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

            $isThinkingModel = str_contains($this->model, 'qwen') || str_contains($this->model, 'deepseek');

            $payload = [
                'model' => $this->model,
                'messages' => $apiMessages,
                'tools' => $this->toolkit->getToolDefinitions(),
                'tool_choice' => 'auto',
                'temperature' => $isThinkingModel ? 0.6 : 0.2,
                'max_tokens' => $isThinkingModel ? 2048 : 1024,
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post($this->apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('AgentOrchestrator: LLM API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 1000),
                    'message_count' => count($apiMessages),
                ]);
                logger()->error("LLM API error: status={$response->status()} body=" . substr($response->body(), 0, 500));
                return null;
            }

            $data = $response->json();
            $choice = $data['choices'][0] ?? null;

            if (!$choice) {
                logger()->error('LLM no choice in response: ' . substr(json_encode($data), 0, 500));
                return null;
            }

            // Strip thinking tags from assistant content
            $msg = $choice['message'];
            $finishReason = $choice['finish_reason'] ?? null;

            if (!empty($msg['content'])) {
                $stripped = trim(preg_replace('/<think>.*?(<\/think>|$)/s', '', $msg['content']));
                $msg['content'] = $stripped;
            }

            // If thinking model consumed all tokens on <think> with no tools
            // and no useful output, return null to let the loop retry or end cleanly
            if (empty($msg['content']) && empty($msg['tool_calls']) && $finishReason !== 'tool_calls') {
                Log::warning('AgentOrchestrator: LLM returned empty after stripping think tags', [
                    'finish_reason' => $finishReason,
                    'tokens' => $data['usage']['total_tokens'] ?? 0,
                ]);
                return null;
            }

            return [
                'message' => $msg,
                'tokens' => $data['usage']['total_tokens'] ?? 0,
                'finish_reason' => $finishReason,
            ];
        } catch (\Exception $e) {
            Log::error('AgentOrchestrator: callLlm exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildApiMessages(array $messages): array
    {
        $total = count($messages);
        $apiMessages = [];

        foreach ($messages as $i => $msg) {
            $apiMsg = ['role' => $msg['role']];

            if (isset($msg['content'])) {
                $content = $msg['content'];

                // Compact old tool responses (keep last 4 messages intact for context)
                if ($msg['role'] === 'tool' && $i < $total - 4 && mb_strlen($content) > 200) {
                    $content = mb_substr($content, 0, 200) . '…[truncated]';
                }

                $apiMsg['content'] = $content;
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

    private function sendTelegramNotification(Bot $bot, AiConversation $conversation): void
    {
        if (!$bot->ai_notify_telegram) {
            return;
        }

        $actions = $conversation->actions_taken ?? [];
        if (empty($actions)) {
            return;
        }

        $allowedEvents = $bot->ai_notify_events ?? ['grid_adjusted', 'bot_stopped', 'stop_loss_set', 'position_closed'];
        $matchingActions = array_intersect($actions, $allowedEvents);

        if (empty($matchingActions)) {
            return;
        }

        $user = $bot->user;
        if (!$user || empty($user->telegram_chat_id)) {
            return;
        }

        try {
            $telegram = app(\App\Services\TelegramService::class);
            $message = $telegram->formatAgentNotification(
                $bot->name,
                $bot->symbol,
                $actions,
                $conversation->summary,
                $conversation->analysis,
            );
            $telegram->sendMessage($user->telegram_chat_id, $message);
        } catch (\Exception $e) {
            Log::warning('AgentOrchestrator: telegram notification failed', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
