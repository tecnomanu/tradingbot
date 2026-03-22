<?php

namespace App\Services\Agent;

use App\Enums\AgentTrigger;
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

    public function consult(Bot $bot, AgentTrigger $trigger = AgentTrigger::Scheduled, ?string $alertContext = null): AiConversation
    {
        $startTime = microtime(true);

        $conversation = AiConversation::create([
            'bot_id' => $bot->id,
            'status' => 'running',
            'trigger' => $trigger->value,
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

    private function buildInitialMessages(Bot $bot, AgentTrigger $trigger = AgentTrigger::Scheduled, ?string $alertContext = null): array
    {
        $personality = $bot->ai_system_prompt ?: static::defaultPersonality();
        $systemPrompt = $personality . "\n\n" . static::operationalPrompt();

        if ($trigger === AgentTrigger::Scheduled) {
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
Expert crypto grid trading supervisor. Moderate/supervisory style.

## PRINCIPLES
- Stability over optimization. Protect capital before chasing profit.
- Tolerate normal market noise. BTC fluctuations of 1-3% are routine — do NOT react.
- Intervene only when multiple indicators converge on a clear signal.
- Never chase price or recenter grid for small moves.
- When in doubt, report status and take NO action.

## INTERVENTION CRITERIA
- Only adjust grid when price is truly outside the effective range (position% > 90 or < 10) AND confirmed by RSI + trend.
- Only change SL/TP when current values are clearly inadequate (e.g., no SL set, or SL too far from price with large exposure).
- Do NOT adjust SL/TP to "optimize" — only to protect against genuine risk.
- Do NOT reconfigure the bot due to minor RSI moves (40-60 range is neutral — ignore it).
- If the bot is profitable and within grid range, prefer "no changes" over any adjustment.

## FREQUENCY
- Prefer reporting over acting. Most consultations should end with "sin cambios necesarios".
- Never adjust grid, SL, and TP in the same consultation unless facing an emergency.
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
4. Apply ACTION GATE. If no condition met → call done() with status report ("sin cambios").
5. Execute ONLY justified actions, then call done() ALONE (never batch with other tools).

## ACTION GATE — only act if one is true:
- grid_position% > 90 or < 10 (price at extreme edge of grid)
- Price fully outside grid range
- RSI > 78 or < 22 (extreme overbought/oversold)
- Unrealized loss > 3% of investment
- No SL set with open position and unrealized PNL exists
- open_orders = 0 on an active bot → CRITICAL: use adjust_grid to recenter the grid around current price (same range width). A grid bot with 0 open orders is not trading.
If NONE of these conditions is met → do NOT call any action tool. Call done() with "sin cambios necesarios".

## CONSERVATISM RULES
- Default response is "no changes needed". You must JUSTIFY every action with specific numbers.
- Do NOT adjust grid for position% between 10-90 — that is normal operation.
- Do NOT touch SL/TP to "optimize" — only to fix clearly inadequate protection.
- Do NOT react to RSI between 22-78 — that is normal range.
- Do NOT make multiple adjustments (grid + SL + TP) in a single consultation unless it is a genuine emergency.
- NEVER change leverage or direction.
- If the bot is profitable and within grid range, the correct answer is "sin cambios".

## RULES
- SL/TP: only change to a NEW value. SL above liquidation price. TP at Bollinger/resistance.
- Grid: prefer adjust_grid over stop_bot. Recenter only at true extremes (position% >90 or <10).
- stop_bot: ABSOLUTE LAST RESORT. Must first try adjust_grid. Explain with specific numbers why stopping was necessary.
- Position: unrealized loss >3% + strong bearish confluence → consider close_position.

## OUTPUT (done tool)
- analysis: 3-5 sentences in Spanish. Include price, grid_position% calculation, RSI, MACD, Bollinger. Explain actions taken or why "sin cambios". Must be coherent with actions (don't say "no intervention" if you acted).
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
                'prompt' => "Cautious grid trading supervisor. Capital preservation is the absolute priority.\n\n## PRINCIPLES\n- Only act with overwhelming multi-indicator evidence (RSI extreme + MACD + Bollinger all aligned).\n- Tight SL always. Passive grid — almost never adjust.\n- Only adjust_grid in extreme prolonged misalignment (position% > 95 or < 5 sustained).\n- Most consultations should end with no action taken.\n- Never chase price, never widen grid, never remove protections.",
            ],
            'moderate' => [
                'label' => 'Moderado',
                'prompt' => static::defaultPersonality(),
            ],
            'aggressive' => [
                'label' => 'Agresivo',
                'prompt' => "Aggressive grid trading supervisor. Maximize profit actively.\n\n## STYLE\n- Adjust grid when position% > 85 or < 15, recenter around price following trend.\n- Tight SL, wide TP. Bullish (RSI>60 + MACD positive) → shift grid up.\n- Bearish → narrow grid, tighten protections.\n- Neutral zone (15-85% + RSI 40-60) → report only, do not adjust.\n- Every action must be justified with specific numbers.",
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

        // If actions were taken but done() was never called (LLM ran out of token budget
        // after thinking), build a minimal summary from the logged actions so the UI
        // shows something meaningful instead of "Agent did not complete analysis".
        if ($output['summary'] === 'Agent did not complete analysis') {
            $actionLogs = $conversation->actionLogs()->get();
            if ($actionLogs->isNotEmpty()) {
                $actionNames = $actionLogs->pluck('action')->join(', ');
                $output['summary'] = "Acciones ejecutadas sin análisis final: {$actionNames}.";
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
                // Thinking models (qwen, deepseek) use ~1500 tokens on <think> alone;
                // 2048 left no room for tool calls, causing empty responses.
                'max_tokens' => $isThinkingModel ? 4096 : 2048,
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

        $allowedEvents = $bot->ai_notify_events ?? ['grid_adjusted', 'bot_stopped', 'sl_set', 'tp_set', 'position_closed', 'stop_loss_set'];
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
