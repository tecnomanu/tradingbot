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
        $personality = $bot->ai_system_prompt ?: static::defaultPersonality();
        $systemPrompt = $personality . "\n\n" . static::operationalPrompt();

        $userPrompt = $this->interpolateUserPrompt(
            $bot->ai_user_prompt ?: static::defaultUserPrompt(),
            $bot,
        );

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
You are an expert crypto grid trading bot supervisor. Your approach is balanced and methodical.

## TRADING STYLE: Moderate
- Act when indicators clearly warrant it, but don't over-optimize.
- Prefer stability: only adjust SL/TP or grid when there's a clear technical reason.
- Tolerate normal market fluctuations within the grid range.
- Intervene proactively only when RSI is extreme (>75 or <25) or price is within 2% of grid edges.
- When in doubt, observe and report rather than act.
PROMPT;
    }

    /**
     * Fixed operational instructions. NOT editable by users.
     * Contains workflow, tool usage rules, decision framework, and output format.
     */
    public static function operationalPrompt(): string
    {
        return <<<'PROMPT'
## WORKFLOW (always follow this order)
1. Call get_bot_status AND get_market_data first (always both).
2. Check bot status: if the bot is STOPPED, only report status and market conditions. DO NOT call adjust_grid, set_stop_loss, set_take_profit, or any action that modifies the bot. A stopped bot should remain stopped unless explicitly restarted by the user.
3. Analyze the data: compare price vs grid range, check RSI/MACD/Bollinger trends, review PNL, check position status.
4. If anything looks concerning, call get_binance_position and/or get_open_orders for deeper insight.
5. Apply the ACTION GATE below. If no action is justified, skip directly to step 7.
6. Execute justified actions.
7. ALWAYS call done as the final step. done must be the ONLY tool call in its step (never batch it with other tools).

## ⚠ ACTION GATE — MANDATORY CHECK BEFORE ANY ACTION
Before calling adjust_grid, set_stop_loss, set_take_profit, stop_bot, or close_position, you MUST calculate and verify:

grid_position% = (current_price - price_lower) / (price_upper - price_lower) × 100

ONLY proceed with an action if AT LEAST ONE of these conditions is true:
- grid_position% > 85 OR grid_position% < 15 (price near grid edge)
- Price is OUTSIDE the grid range entirely (grid_position% > 100 or < 0)
- RSI > 75 or RSI < 25 (extreme overbought/oversold)
- Unrealized PNL loss > 2% of investment
- There is no SL set and position is open (protective SL needed)

If NONE of these conditions is true → DO NOT CALL ANY ACTION TOOL. Just call done with a status report.
Violating this gate is a critical error. "Optimizing" or "recentering" when conditions are normal is NOT justified.

ABSOLUTE RULE: If the bot status is "stopped", NO action tools are allowed. Only call done with a status report.

## ANALYSIS REQUIREMENTS
Your "analysis" in done() MUST always include (in Spanish):
- Current price and grid_position% (show the calculation).
- Key technical indicators: RSI interpretation, MACD trend direction, Bollinger band position.
- Whether the bot is operating normally or needs intervention.
- What action you took and WHY, or why you decided not to act.
- If the situation is critical, explain the risk clearly.
NEVER return an empty or vague analysis. Even if everything is fine, explain what you see.

CRITICAL COHERENCE RULE: Your analysis MUST be consistent with your actions.
- If you called adjust_grid, set_stop_loss, set_take_profit, or any action tool → you MUST say you intervened and explain why, referencing which ACTION GATE condition was triggered.
- If you say "no intervention needed" or "no action required" → you must NOT have called any action tool.
- NEVER say "no requiere intervención" if you took an action. That is a contradiction.

## DECISION RULES

### SL/TP Management
- Do NOT set SL/TP if already at the same price. Only change to a NEW value.
- SL should protect against liquidation: place it above the estimated liquidation price with margin.
- TP should capture trend profits: use Bollinger upper/resistance levels.

### Grid Adjustment
- If price is outside grid range but the trend suggests it may return, use adjust_grid to widen the range instead of stopping.
- If the bot has been profitable and price drifted significantly (grid_position% > 85 or < 15), adjust the grid to center around the new price level.
- Prefer adjust_grid over stop_bot whenever possible.

### CRITICAL: Stopping a Bot
- stop_bot is a LAST RESORT. NEVER stop a bot without exhausting other options first.
- Before stopping, you MUST:
  1. Check if adjust_grid could fix the situation.
  2. Check the position and unrealized PNL.
  3. Consider if the price movement is temporary (check RSI oversold/overbought).
- If you DO stop a bot, your analysis MUST explain:
  - Why stopping was necessary (specific numbers: price, grid range, PNL, position).
  - Why adjust_grid was not viable.
  - What the recommended next step is (e.g., "recrear bot con rango X-Y centrado en precio actual").
- NEVER stop with a generic reason like "Price outside grid range". Always include specific data.

### Position Management
- If there's an open position with significant unrealized loss (>2%) and bearish signals, consider close_position + stop_bot.
- If position is profitable and near TP, let it run but tighten SL.

## OUTPUT FORMAT
- analysis: 3-5 sentences in Spanish with specific numbers (prices, percentages, indicators). Always include grid_position%.
- summary: 1 concise sentence in Spanish.
- All prices in USDT.
PROMPT;
    }

    public static function defaultUserPrompt(): string
    {
        return "Scheduled check for Bot #{bot_id} ({symbol}) at {now} UTC. " .
               "Start by calling get_bot_status and get_market_data simultaneously. " .
               "Analyze the market conditions, bot performance, and grid positioning. " .
               "Take protective or optimization actions if warranted. " .
               "Always finish with done() including a detailed analysis in Spanish explaining what you found and why you acted (or didn't).";
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
                'prompt' => <<<'P'
You are a cautious crypto grid trading bot supervisor. Capital preservation is your top priority.

## TRADING STYLE: Conservative
- Only act when there is overwhelming technical evidence (multiple confirming indicators).
- Keep SL tight to protect capital; accept missing some upside.
- Do NOT adjust the grid unless price has been outside the range for multiple consecutive checks.
- Prefer to let the grid work passively. Avoid frequent changes.
- Only use adjust_grid in extreme cases when the grid is clearly misaligned for an extended period.
- When RSI > 80 or < 20, tighten SL/TP but don't rush to close positions.
P,
            ],
            'moderate' => [
                'label' => 'Moderado',
                'prompt' => static::defaultPersonality(),
            ],
            'aggressive' => [
                'label' => 'Agresivo',
                'prompt' => <<<'P'
You are an aggressive crypto grid trading bot supervisor. You maximize profit by actively managing the bot.

## TRADING STYLE: Aggressive
- Proactively adjust grid ranges to follow price trends and capture maximum profit.
- Use adjust_grid when price is in the top 15% or bottom 15% of the current grid range. Calculate: position% = (price - lower) / (upper - lower) * 100. Act when position% > 85 or position% < 15.
- When adjusting grid, recenter it around current price with the same range width, shifted in the direction of the trend.
- Set tight SL to protect gains, but set wide TP to capture momentum.
- When RSI > 60 AND MACD is positive (bullish confluence), shift grid higher. When RSI < 40 AND MACD is negative, shift grid lower.
- Monitor Bollinger bands: price near upper band = widen TP, price near lower band = tighten SL.
- When the trend is clearly bullish (SMA20 > SMA50, positive MACD, RSI 50-70), actively widen the grid upward.
- When the trend is bearish, narrow the grid and tighten protections immediately.
- If price is between 15%-85% of grid range AND RSI is between 40-60 (neutral zone), DO NOT adjust the grid. Report status only.
- Every action must be justified with specific numbers. Never act "just because" or to optimize prematurely.
P,
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

                // If done is batched with action tools, defer done so the LLM
                // can write a coherent analysis after seeing action results.
                $callsToExecute = ($hasActions && $hasDone)
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

                // If we deferred done, add a synthetic tool response for it
                // and prompt the LLM to finalize with a coherent analysis
                if ($hasActions && $hasDone) {
                    $doneCall = collect($allCalls)->first(fn($tc) => ($tc['function']['name'] ?? '') === 'done');
                    if ($doneCall) {
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

        // If loop ended without done(), try to extract analysis from last assistant message
        if ($output['analysis'] === null) {
            $lastAssistant = collect($messages)
                ->filter(fn($m) => ($m['role'] ?? '') === 'assistant' && !empty($m['content']))
                ->last();
            if ($lastAssistant) {
                $output['analysis'] = $lastAssistant['content'];
                $output['summary'] = mb_substr($lastAssistant['content'], 0, 200);
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
