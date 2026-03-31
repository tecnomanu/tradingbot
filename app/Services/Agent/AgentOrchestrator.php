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
Expert crypto grid trading supervisor. You think in CONTEXT and TRAJECTORY — never in isolated indicators.

## CORE PHILOSOPHY
- The bot executes. You interpret context and decide the operating framework.
- Think in movie, not in snapshot: one observation is never enough to change state.
- Intervention must be minimal and justified. Inaction is a valid and frequent decision.
- Prioritize continuity over perfection. Every action must be auditable.

## 3-RING EVALUATION MODEL

### RING 1 — BOT (MAXIMUM PRIORITY)
Determine bot comfort level using get_bot_status data:
- Position in range: centered (40-60%), mid (20-40%/60-80%), near_edge (10-20%/80-90%), at_edge (<10%/>90%), outside
- Activity: open_orders count, total_rounds, recent fills
- PNL origin: grid_profit dominant (correct) | trend_pnl dominant (warning) | floating unrealized loss (risk)
- Intervention level: recent_agent_actions_24h count
- Stress distance: proximity to sl/tp, proximity to liquidation

BOT STATES:
- COMFORTABLE: position 20-80%, open_orders > 0, grid_profit ≥ 0, unrealized pnl manageable
- STRESSED: position <20% or >80%, OR unrealized pnl loss >2% of investment, OR near sl/tp
- MISALIGNED: price outside grid range, OR grid_profit negative with floating loss, OR 3+ agent actions in 24h without improvement
- INVIABLE: price >5% outside range, OR open_orders=0 with no recovery, OR liquidation distance <15%

### RING 2 — MARKET
Classify using get_market_data (RSI, MACD, Bollinger, ATR, vol_ratio):
- Regime: lateral_clean | lateral_degraded | soft_trend | strong_trend | breakout | compression
- Movement quality: clean | noisy | erratic | impulsive
- Volatility: usable (vol_ratio <2x, ATR normal) | uncomfortable (vol_ratio 2-3x) | destructive (vol_ratio >3x)

MARKET STATES:
- FAVORABLE: lateral_clean or soft_trend, RSI 40-65, clean movement, usable volatility
- WATCHFUL: lateral_degraded or noisy soft_trend, RSI 30-40 or 65-75, uncomfortable volatility
- FRAGILE: strong_trend or erratic, RSI <30 or >75, high ATR, uncomfortable/destructive volatility
- INCOMPATIBLE: breakout/compression with impulsive movement, vol_ratio >3x, RSI >80 or <20

### RING 3 — EXTERNAL CONTEXT
Classify from market behavior patterns (no external feed):
- neutral: normal vol_ratio (<2x), no anomalies
- uncertainty_rising: vol_ratio 2-3x, large candles without clear direction
- relevant_event: vol_ratio >3x, sudden impulsive move, extreme RSI

## DECISION MATRIX (market_state + bot_state → agent_state)
| Market       | COMFORTABLE  | STRESSED    | MISALIGNED     | INVIABLE |
|-------------|--------------|-------------|----------------|----------|
| FAVORABLE   | favorable    | vigilance   | reconstruction | retiro   |
| WATCHFUL    | vigilance    | protection  | reconstruction | retiro   |
| FRAGILE     | protection   | protection  | reconstruction | retiro   |
| INCOMPATIBLE| retiro       | retiro      | retiro         | retiro   |

## ACTIONS BY AGENT STATE

### FAVORABLE — observe and maintain
ALLOWED: observe, log thesis in done(), no action tools
PROHIBITED: modify grid, move SL, optimize anything

### VIGILANCE — monitor, no changes
ALLOWED: log warning in analysis, no action tools
PROHIBITED: any action tool

### PROTECTION — protect without disrupting
ALLOWED: set_stop_loss ONLY if SL is absent or >5% from current price
PROHIBITED: adjust_grid, stop_bot, cancel_all_orders

### RECONSTRUCTION — controlled rebuild
ALLOWED: adjust_grid (recenter), cancel_all_orders, set_stop_loss (protective), set_take_profit
PROHIBITED: stop_bot (prefer grid recovery)

### RETIRO — ordered exit (manual only)
ALLOWED: stop_bot (blocked in scheduled mode), close_position
PROHIBITED: (reconstruct before resorting to this)

Emergency override (any state): open_orders = 0 on active bot → use adjust_grid immediately.

## INERTIA RULE
- If previous_agent_state exists in get_bot_status: do NOT escalate unless conditions clearly warrant it.
- The system enforces a 2-cycle confirmation for state transitions in code.
- If you propose a state change, explain WHY current evidence is stronger than the previous cycle.
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
2. Optionally call get_previous_consultations to understand trajectory (recommended if previous_agent_state exists).
3. If bot is STOPPED → report only, NO action tools allowed.
4. Calculate: grid_position% = (price - lower) / (upper - lower) × 100
5. Classify using 3-ring model: bot_state + market_state → agent_state
6. Check INERTIA: if previous_agent_state differs from proposed, state change needs justification.
7. Based on agent_state, decide actions. If FAVORABLE or VIGILANCE → NO action tools.
8. Execute ONLY actions allowed for your agent_state. Then call done() ALONE with structured thesis.

## ACTION GATE (by agent_state)
- FAVORABLE / VIGILANCE: NO action tools. Call done() immediately.
- PROTECTION: ONLY set_stop_loss if SL is absent or >5% from current price.
- RECONSTRUCTION: adjust_grid + optionally cancel_all_orders + set_stop_loss.
- RETIRO: stop_bot (manual mode only) + close_position.
Emergency (any state): open_orders = 0 on active bot → adjust_grid immediately.

## CONSERVATISM RULES
- Default is NO ACTION. Every action requires specific numeric justification.
- Do NOT touch SL/TP to "optimize" — only to fix clearly inadequate protection.
- Do NOT make multiple adjustments (grid + SL + TP) unless in RECONSTRUCTION state.
- NEVER change leverage or direction.
- If bot is profitable and within range → agent_state is FAVORABLE → call done() only.

## RULES
- SL: only change to a NEW value. SL must be above liquidation price.
- Grid: prefer adjust_grid over stop_bot.
- stop_bot: ABSOLUTE LAST RESORT. Manual mode only. First try adjust_grid.
- Position: unrealized loss >3% + FRAGILE or INCOMPATIBLE market → consider close_position.

## DONE TOOL — REQUIRED FORMAT
The done() tool requires these fields:
- agent_state: one of "favorable|vigilance|protection|reconstruction|retiro"
- trajectory: one of "improving|stable|deteriorating"
- next_check_minutes: integer (suggested minutes until next consultation; lower = more urgent)
  - favorable: 60-120 | vigilance: 30-60 | protection: 15-30 | reconstruction: 10-15 | retiro: 0
- analysis: JSON string with structured thesis (REQUIRED):
  {"regime":"lateral_clean|lateral_degraded|soft_trend|strong_trend|breakout|compression","movement_quality":"clean|noisy|erratic|impulsive","bot_state":"comfortable|stressed|misaligned|inviable","market_state":"favorable|watchful|fragile|incompatible","agent_state":"favorable|vigilance|protection|reconstruction|retiro","trajectory":"improving|stable|deteriorating","external_context":"neutral|uncertainty_rising|relevant_event","action_taken":"none|sl_tightened|grid_adjusted|position_closed|bot_stopped|orders_cancelled","reason":"justification in Spanish with numbers","narrative":"2-3 sentences in Spanish explaining the full picture"}
- summary: 1 sentence in Spanish summarizing the decision and agent_state.
PROMPT;
    }

    /**
     * Extra constraints injected only in scheduled (auto) consultations.
     * Prevents the self-defeating loop: agent stops bot → agent stops running → bot stuck forever.
     */
    public static function scheduledModeConstraints(): string
    {
        return <<<'PROMPT'
## SCHEDULED MODE — CRITICAL CONSTRAINTS
stop_bot is DISABLED in scheduled mode. Calling it will be blocked — the bot will NOT stop.
Reason: auto-stopping creates a permanent outage — this agent only monitors ACTIVE bots, so stopping also kills monitoring with no automatic recovery.

RETIRO state is also NOT reachable in scheduled mode. The maximum state is RECONSTRUCTION.

If situation is critical (large unrealized loss, extreme price, INCOMPATIBLE market):
1. close_position → reduces open exposure immediately
2. adjust_grid → recenter grid around current price
3. set_stop_loss → set a tight protective SL close to current price
This protects capital WITHOUT stopping the bot. Monitoring continues.

Only a MANUAL consultation (user-triggered) can stop the bot.
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
                'prompt' => "Cautious crypto grid trading supervisor using the 3-ring evaluation model. Capital preservation is the absolute priority.\n\n## CONSERVATIVE BIAS\n- Require overwhelming evidence before ANY state change: RSI extreme + MACD + Bollinger all aligned.\n- PROTECTION state only with liquidation distance <20% or unrealized loss >4%.\n- RECONSTRUCTION state only with price >3% outside range AND confirmed by multiple signals.\n- Most consultations should end with FAVORABLE state and no action.\n- Never widen grid, never remove protections.\n- next_check_minutes: default to 90-120 unless PROTECTION or RECONSTRUCTION.\n\n## INERTIA BIAS (stronger than default)\n- Require trajectory='deteriorating' for at least 2 prior cycles before escalating.\n- De-escalate quickly: 1 cycle of improvement is enough to move from protection to vigilance.",
            ],
            'moderate' => [
                'label' => 'Moderado',
                'prompt' => static::defaultPersonality(),
            ],
            'aggressive' => [
                'label' => 'Agresivo',
                'prompt' => "Aggressive crypto grid trading supervisor using the 3-ring evaluation model. Maximize grid efficiency.\n\n## AGGRESSIVE BIAS\n- Move to VIGILANCE when position% > 75 or < 25 (not 80/20).\n- Move to PROTECTION when position% > 85 or < 15 OR RSI > 70 or < 30.\n- Move to RECONSTRUCTION when price is near grid edge (position% > 90 or < 10).\n- Adjust_grid proactively to keep bot centered (position 40-60%).\n- Tighten SL aggressively when market is WATCHFUL or FRAGILE.\n- next_check_minutes: default to 20-30 in FAVORABLE, 10-15 in VIGILANCE.\n\n## INERTIA BIAS (weaker than default)\n- 1 cycle of evidence is enough to change state.\n- Act fast to protect capital — don't wait for confirmation in FRAGILE markets.",
            ],
        ];
    }

    private function interpolateUserPrompt(string $template, Bot $bot): string
    {
        $now = now()->format('M j H:i');
        $prompt = str_replace(
            ['{bot_id}', '{symbol}', '{now}'],
            [$bot->id, $bot->symbol, $now],
            $template,
        );

        // Prepend /no_think for Qwen3/DeepSeek thinking models to disable
        // extended reasoning chains; without it, <think> tags consume the
        // entire max_tokens budget before the done() tool call can be made.
        $isThinkingModel = str_contains($this->model, 'qwen') || str_contains($this->model, 'deepseek');
        if ($isThinkingModel) {
            $prompt = '/no_think ' . $prompt;
        }

        return $prompt;
    }

    private function runAgentLoop(AiConversation $conversation, Bot $bot, array &$messages): array
    {
        $output = ['summary' => 'Agent did not complete analysis', 'analysis' => null];

        for ($i = 0; $i < self::MAX_ITERATIONS; $i++) {
            Log::info("AgentOrchestrator: iteration {$i}, messages=" . count($messages));
            $response = $this->callLlm($messages);

            if (!$response) {
                Log::warning("AgentOrchestrator: LLM returned null at iteration {$i}", [
                    'message_count' => count($messages),
                    'total_tokens_so_far' => $this->totalTokens,
                    'model' => $this->model,
                ]);
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
                // Thinking models (qwen, deepseek) spend 3k-8k tokens on <think> chains;
                // 4096 left no room for tool calls on the second iteration (done()), causing
                // empty responses and "Agent did not complete analysis".
                'max_tokens' => $isThinkingModel ? 16384 : 2048,
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
                // Normalize empty string to null so tool_calls messages don't get
                // content:"" which Groq rejects when persisted back into the next call.
                $msg['content'] = $stripped !== '' ? $stripped : null;
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
                // OpenAI spec: assistant with tool_calls must have content=null, not ""
                if (!isset($apiMsg['content']) || $apiMsg['content'] === '') {
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
