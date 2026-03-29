<?php

namespace App\Services\Agent;

use App\Constants\BinanceConstants;
use App\Enums\AgentTrigger;
use App\Enums\BotStatus;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\AiConversation;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Services\AiTradingAgent;
use App\Services\BinanceApiService;
use App\Services\BinanceFuturesService;
use App\Services\BotActivityLogger;
use App\Services\BotService;
use App\Services\GridTradingEngine;
use App\Support\BotLog as Log;
use Illuminate\Support\Facades\Cache;

class AgentToolkit
{
    private ?int $conversationId = null;
    private AgentTrigger $trigger = AgentTrigger::Scheduled;

    public function __construct(
        private GridTradingEngine $engine,
        private BinanceApiService $binanceApi,
        private BinanceFuturesService $binanceFutures,
        private AiTradingAgent $aiAgent,
        private BotService $botService,
    ) {}

    public function setConversationId(int $id): void
    {
        $this->conversationId = $id;
    }

    public function setTrigger(AgentTrigger $trigger): void
    {
        $this->trigger = $trigger;
    }

    /**
     * OpenAI-compatible tool definitions for function calling.
     */
    public function getToolDefinitions(): array
    {
        return [
            $this->tool('get_bot_status', 'Bot config, PNL, grid, SL/TP, orders, previous agent_state, recent interventions.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('get_market_data', 'Price, RSI, SMA, MACD, Bollinger, ATR, vol_ratio, external_context classification.', [
                'symbol' => ['type' => 'string'],
            ]),
            $this->tool('get_open_orders', 'Open orders summary.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('get_filled_orders', 'Recent fills + total PNL.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('get_binance_position', 'Live position data including unrealized PNL and liquidation.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('get_previous_consultations', 'Last N agent consultations: thesis, state, actions taken. Use to understand trajectory.', [
                'bot_id' => ['type' => 'integer'],
                'limit' => ['type' => 'integer', 'description' => 'Number of past consultations to fetch (1-5, default 3)'],
            ]),
            $this->tool('set_stop_loss', 'Set SL price. PROTECTION and RECONSTRUCTION states only.', [
                'bot_id' => ['type' => 'integer'],
                'price' => ['type' => 'number'],
            ]),
            $this->tool('set_take_profit', 'Set TP price.', [
                'bot_id' => ['type' => 'integer'],
                'price' => ['type' => 'number'],
            ]),
            $this->tool('cancel_all_orders', 'Cancel all open orders. RECONSTRUCTION state only.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('adjust_grid', 'Adjust grid range. Preferred over stop. RECONSTRUCTION state or emergency (0 open orders).', [
                'bot_id' => ['type' => 'integer'],
                'new_price_lower' => ['type' => 'number'],
                'new_price_upper' => ['type' => 'number'],
                'reason' => ['type' => 'string', 'description' => 'Why: price_outside_range | volatility_shift | trend_change | protection_mode | bot_recovery'],
            ]),
            $this->tool('stop_bot', 'Stop bot. ABSOLUTE LAST RESORT. Manual mode only. RETIRO state only.', [
                'bot_id' => ['type' => 'integer'],
                'reason' => ['type' => 'string'],
            ]),
            $this->tool('close_position', 'Market-close position. RETIRO or RECONSTRUCTION with extreme loss.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            // done() has custom schema (not all params are required)
            [
                'type' => 'function',
                'function' => [
                    'name' => 'done',
                    'description' => 'Finish consultation. Provide structured thesis (agent_state, trajectory, next_check_minutes) + JSON analysis + summary.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'agent_state' => [
                                'type' => 'string',
                                'enum' => ['favorable', 'vigilance', 'protection', 'reconstruction', 'retiro'],
                                'description' => 'Resulting agent state from decision matrix',
                            ],
                            'trajectory' => [
                                'type' => 'string',
                                'enum' => ['improving', 'stable', 'deteriorating'],
                                'description' => 'Trend of bot health over recent cycles',
                            ],
                            'next_check_minutes' => [
                                'type' => 'integer',
                                'description' => 'Suggested minutes until next consultation. favorable:60-120, vigilance:30-60, protection:15-30, reconstruction:10-15',
                            ],
                            'analysis' => [
                                'type' => 'string',
                                'description' => 'JSON string with structured thesis: regime, movement_quality, bot_state, market_state, agent_state, trajectory, external_context, action_taken, reason, narrative',
                            ],
                            'summary' => [
                                'type' => 'string',
                                'description' => '1 sentence in Spanish summarizing the decision and agent_state',
                            ],
                        ],
                        'required' => ['agent_state', 'trajectory', 'analysis', 'summary'],
                    ],
                ],
            ],
        ];
    }

    private function tool(string $name, string $desc, array $props): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $desc,
                'parameters' => [
                    'type' => 'object',
                    'properties' => $props,
                    'required' => array_keys($props),
                ],
            ],
        ];
    }

    private const ACTION_TOOLS = ['adjust_grid', 'set_stop_loss', 'set_take_profit', 'cancel_all_orders', 'stop_bot', 'close_position'];

    public function executeTool(string $name, array $args, Bot $bot): array
    {
        // Hard block: never modify a stopped bot
        if (in_array($name, self::ACTION_TOOLS) && $bot->status === BotStatus::Stopped) {
            return ['error' => 'Bot is stopped. Cannot execute action tools on a stopped bot. Only reporting tools are allowed.'];
        }

        try {
            return match ($name) {
                'get_market_data' => $this->toolGetMarketData($args),
                'get_bot_status' => $this->toolGetBotStatus($bot),
                'get_open_orders' => $this->toolGetOpenOrders($bot),
                'get_filled_orders' => $this->toolGetFilledOrders($bot, $args),
                'get_binance_position' => $this->toolGetPosition($bot),
                'get_previous_consultations' => $this->toolGetPreviousConsultations($bot, $args),
                'set_stop_loss' => $this->toolSetStopLoss($bot, $args),
                'set_take_profit' => $this->toolSetTakeProfit($bot, $args),
                'adjust_grid' => $this->toolAdjustGrid($bot, $args),
                'cancel_all_orders' => $this->toolCancelAllOrders($bot),
                'stop_bot' => $this->toolStopBot($bot, $args),
                'close_position' => $this->toolClosePosition($bot),
                'done' => $this->toolDone($bot, $args),
                default => ['error' => "Unknown tool: {$name}"],
            };
        } catch (\Exception $e) {
            Log::error("AgentToolkit: tool {$name} failed", ['error' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    private function toolGetMarketData(array $args): array
    {
        $symbol = $args['symbol'] ?? 'BTCUSDT';
        $data = $this->aiAgent->gatherMarketData($symbol);

        if (!$data) {
            return ['error' => 'Could not fetch market data'];
        }

        // Classify external context from market behavior (Ring 3 — no external feed needed)
        $volRatio = (float) ($data['vol_ratio'] ?? 1.0);
        $chg24h = abs((float) ($data['chg24h'] ?? 0));

        if ($volRatio > 3.0 || ($volRatio > 2.5 && $chg24h > 5.0)) {
            $externalContext = 'relevant_event';
        } elseif ($volRatio > 2.0 || $chg24h > 3.0) {
            $externalContext = 'uncertainty_rising';
        } else {
            $externalContext = 'neutral';
        }

        $data['external_context'] = $externalContext;

        return $data;
    }

    private function toolGetBotStatus(Bot $bot): array
    {
        $bot->refresh();

        $recentAgentActions = BotActionLog::where('bot_id', $bot->id)
            ->where('source', 'agent')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $totalPnl = (float) $bot->total_pnl;
        $gridProfit = (float) $bot->grid_profit;
        $trendPnl = (float) $bot->trend_pnl;

        $pnlOrigin = 'none';
        if ($totalPnl != 0) {
            $gridRatio = $totalPnl > 0 ? ($gridProfit / $totalPnl) : 0;
            if ($totalPnl < 0) {
                $pnlOrigin = 'floating_loss';
            } elseif ($gridRatio >= 0.7) {
                $pnlOrigin = 'from_grid';
            } elseif (abs($trendPnl) > abs($gridProfit)) {
                $pnlOrigin = 'from_trend';
            } else {
                $pnlOrigin = 'mixed';
            }
        }

        return [
            'status' => $bot->status->value,
            'symbol' => $bot->symbol,
            'side' => $bot->side,
            'leverage' => $bot->leverage,
            'lower' => (float) $bot->price_lower,
            'upper' => (float) $bot->price_upper,
            'grids' => $bot->grid_count,
            'investment' => (float) $bot->investment,
            'pnl' => $totalPnl,
            'grid_profit' => $gridProfit,
            'trend_pnl' => $trendPnl,
            'pnl_origin' => $pnlOrigin,
            'rounds' => $bot->total_rounds,
            'sl' => $bot->stop_loss_price ? (float) $bot->stop_loss_price : null,
            'tp' => $bot->take_profit_price ? (float) $bot->take_profit_price : null,
            'open_orders' => $bot->orders()->where('status', OrderStatus::Open)->count(),
            'filled_orders' => $bot->orders()->where('status', OrderStatus::Filled)->count(),
            'agent_state' => $bot->agent_state,
            'agent_state_streak' => (int) $bot->agent_state_streak,
            'recent_agent_actions_24h' => $recentAgentActions,
        ];
    }

    private function toolGetOpenOrders(Bot $bot): array
    {
        $orders = $bot->orders()
            ->where('status', OrderStatus::Open)
            ->orderBy('price')
            ->get(['side', 'price', 'grid_level']);

        $buys = $orders->filter(fn($o) => $o->side === OrderSide::Buy);
        $sells = $orders->filter(fn($o) => $o->side === OrderSide::Sell);

        return [
            'total' => $orders->count(),
            'buys' => $buys->count(),
            'sells' => $sells->count(),
            'buy_range' => $buys->count() ? round($buys->min('price'), 1) . '-' . round($buys->max('price'), 1) : null,
            'sell_range' => $sells->count() ? round($sells->min('price'), 1) . '-' . round($sells->max('price'), 1) : null,
            'gap_levels' => $this->findGapLevels($orders, $bot->grid_count),
        ];
    }

    private function findGapLevels($orders, int $gridCount): array
    {
        $activeLevels = $orders->pluck('grid_level')->toArray();
        $gaps = [];
        for ($i = 0; $i <= $gridCount; $i++) {
            if (!in_array($i, $activeLevels)) {
                $gaps[] = $i;
            }
        }
        return $gaps;
    }

    private function toolGetPreviousConsultations(Bot $bot, array $args): array
    {
        $limit = min((int) ($args['limit'] ?? 3), 5);
        $limit = max($limit, 1);

        $consultations = AiConversation::where('bot_id', $bot->id)
            ->where('status', 'completed')
            ->latest('ended_at')
            ->limit($limit)
            ->get(['id', 'ended_at', 'trigger', 'summary', 'analysis', 'actions_taken']);

        return [
            'count' => $consultations->count(),
            'consultations' => $consultations->map(fn ($c) => [
                'id' => $c->id,
                'when' => $c->ended_at?->diffForHumans(),
                'trigger' => $c->trigger,
                'summary' => $c->summary,
                'analysis' => $c->analysis,
                'actions' => $c->actions_taken ?? [],
            ])->toArray(),
        ];
    }

    private function toolDone(Bot $bot, array $args): array
    {
        $proposedState = $args['agent_state'] ?? null;
        $trajectory = $args['trajectory'] ?? 'stable';
        $nextCheckMinutes = isset($args['next_check_minutes']) ? (int) $args['next_check_minutes'] : null;

        $finalState = $this->applyInertiaAndPersist($bot, $proposedState, $trajectory, $nextCheckMinutes);

        return [
            'ok' => true,
            'analysis' => $args['analysis'] ?? null,
            'agent_state_applied' => $finalState,
            'agent_state_proposed' => $proposedState,
            'inertia_blocked' => ($finalState !== $proposedState),
        ];
    }

    /**
     * Apply inertia logic and persist agent state + next consultation time.
     * State transitions require 2 consecutive observations (pending confirmation).
     * Returns the final confirmed state.
     */
    private function applyInertiaAndPersist(Bot $bot, ?string $proposedState, string $trajectory, ?int $nextCheckMinutes): ?string
    {
        if (!$proposedState) {
            return $bot->agent_state;
        }

        $confirmedState = $bot->agent_state;

        // First consultation: no inertia needed, set directly
        if ($confirmedState === null) {
            $updates = [
                'agent_state' => $proposedState,
                'agent_state_streak' => 1,
            ];
            if ($nextCheckMinutes > 0) {
                $updates['ai_next_consultation_at'] = now()->addMinutes($nextCheckMinutes);
            }
            $bot->update($updates);
            $bot->refresh();
            return $proposedState;
        }

        // Same state as confirmed: increment streak, no transition
        if ($proposedState === $confirmedState) {
            $updates = ['agent_state_streak' => min((int) $bot->agent_state_streak + 1, 100)];
            if ($nextCheckMinutes > 0) {
                $updates['ai_next_consultation_at'] = now()->addMinutes($nextCheckMinutes);
            }
            $bot->update($updates);
            $bot->refresh();
            return $confirmedState;
        }

        // Different state proposed: check pending confirmation via Cache
        $pendingKey = "agent_state_pending.{$bot->id}";
        $pendingState = Cache::get($pendingKey);

        if ($pendingState === $proposedState) {
            // Second consecutive proposal for the same new state: CONFIRM transition
            Cache::forget($pendingKey);
            $updates = [
                'agent_state' => $proposedState,
                'agent_state_streak' => 0,
            ];
            if ($nextCheckMinutes > 0) {
                $updates['ai_next_consultation_at'] = now()->addMinutes($nextCheckMinutes);
            }
            $bot->update($updates);
            $bot->refresh();

            Log::info('AgentToolkit: state transition confirmed (inertia)', [
                'bot_id' => $bot->id,
                'from' => $confirmedState,
                'to' => $proposedState,
                'trajectory' => $trajectory,
            ]);

            return $proposedState;
        }

        // First proposal for a new state: store as pending, block transition
        Cache::put($pendingKey, $proposedState, now()->addMinutes(90));
        if ($nextCheckMinutes > 0) {
            $bot->update(['ai_next_consultation_at' => now()->addMinutes($nextCheckMinutes)]);
        }

        Log::info('AgentToolkit: state transition pending (inertia block)', [
            'bot_id' => $bot->id,
            'confirmed' => $confirmedState,
            'proposed' => $proposedState,
            'trajectory' => $trajectory,
        ]);

        return $confirmedState;
    }

    private function toolGetFilledOrders(Bot $bot, array $args): array
    {
        $allFilled = $bot->orders()->where('status', OrderStatus::Filled);
        $totalCount = $allFilled->count();
        $totalPnl = round((clone $allFilled)->sum('pnl'), 4);

        $recent = $bot->orders()
            ->where('status', OrderStatus::Filled)
            ->orderByDesc('filled_at')
            ->limit(3)
            ->get(['side', 'price', 'pnl', 'grid_level', 'filled_at']);

        return [
            'total_filled' => $totalCount,
            'total_pnl' => $totalPnl,
            'last_3' => $recent->map(fn($o) => [
                'side' => $o->side->value,
                'price' => round((float) $o->price, 1),
                'pnl' => round((float) $o->pnl, 4),
                'level' => $o->grid_level,
                'ago' => $o->filled_at ? \Carbon\Carbon::parse($o->filled_at)->diffForHumans() : null,
            ])->toArray(),
        ];
    }

    private function toolGetPosition(Bot $bot): array
    {
        $account = $bot->binanceAccount;
        if (!$account) {
            return ['error' => 'No Binance account linked'];
        }

        $positions = $this->binanceApi->getPositions($account, $bot->symbol);
        if (empty($positions)) {
            return ['has_position' => false, 'message' => 'No open position'];
        }

        $pos = $positions[0];
        return [
            'has_position' => (float) ($pos['positionAmt'] ?? 0) != 0,
            'size' => (float) ($pos['positionAmt'] ?? 0),
            'entry_price' => (float) ($pos['entryPrice'] ?? 0),
            'unrealized_pnl' => (float) ($pos['unRealizedProfit'] ?? 0),
            'liquidation_price' => (float) ($pos['liquidationPrice'] ?? 0),
            'leverage' => (int) ($pos['leverage'] ?? 0),
            'margin_type' => $pos['marginType'] ?? 'unknown',
        ];
    }

    private function toolSetStopLoss(Bot $bot, array $args): array
    {
        $price = (float) ($args['price'] ?? 0);
        if ($price <= 0) {
            return ['error' => 'Invalid SL price'];
        }

        $before = (float) $bot->stop_loss_price;
        if (abs($price - $before) < 0.01) {
            return ['skipped' => true, 'message' => "SL already at {$price}", 'stop_loss_price' => $before];
        }

        $bot->update(['stop_loss_price' => $price]);

        $this->logAction($bot, 'sl_set', [
            'reason' => 'agent_decision',
            'price' => $price,
            'previous' => $before ?: null,
        ]);

        return ['success' => true, 'stop_loss_price' => $price, 'previous' => $before ?: null];
    }

    private function toolSetTakeProfit(Bot $bot, array $args): array
    {
        $price = (float) ($args['price'] ?? 0);
        if ($price <= 0) {
            return ['error' => 'Invalid TP price'];
        }

        $before = (float) $bot->take_profit_price;
        if (abs($price - $before) < 0.01) {
            return ['skipped' => true, 'message' => "TP already at {$price}", 'take_profit_price' => $before];
        }

        $bot->update(['take_profit_price' => $price]);

        $this->logAction($bot, 'tp_set', [
            'reason' => 'agent_decision',
            'price' => $price,
            'previous' => $before ?: null,
        ]);

        return ['success' => true, 'take_profit_price' => $price, 'previous' => $before ?: null];
    }

    private const VALID_GRID_REASONS = [
        'price_outside_range',
        'volatility_shift',
        'trend_change',
        'protection_mode',
        'bot_recovery',
    ];

    private function toolAdjustGrid(Bot $bot, array $args): array
    {
        $newLower = (float) ($args['new_price_lower'] ?? 0);
        $newUpper = (float) ($args['new_price_upper'] ?? 0);

        if ($newLower <= 0 || $newUpper <= 0 || $newLower >= $newUpper) {
            return ['error' => 'Invalid price range: lower must be > 0 and < upper'];
        }

        $reason = $args['reason'] ?? 'unknown';
        if (!in_array($reason, self::VALID_GRID_REASONS, true)) {
            $reason = 'unknown';
        }

        $oldLower = (float) $bot->price_lower;
        $oldUpper = (float) $bot->price_upper;
        $beforeState = BotActivityLogger::captureState($bot);

        $account = $bot->binanceAccount;
        if (!$account) {
            return ['error' => 'No Binance account linked'];
        }

        try {
            $this->binanceFutures->cancelAllOrders($account, $bot->symbol);
            $bot->orders()->where('status', OrderStatus::Open)->update(['status' => OrderStatus::Cancelled]);

            $bot->update([
                'price_lower' => $newLower,
                'price_upper' => $newUpper,
            ]);

            $freshBot = $bot->fresh();
            $this->engine->reinitializeGrid($freshBot);

            $afterState = BotActivityLogger::captureState($freshBot);

            $this->logAction($bot, 'grid_adjusted', [
                'reason' => $reason,
                'old_range' => "{$oldLower}-{$oldUpper}",
                'new_range' => "{$newLower}-{$newUpper}",
            ], $beforeState, $afterState);

            return [
                'success' => true,
                'reason' => $reason,
                'old_range' => "{$oldLower}-{$oldUpper}",
                'new_range' => "{$newLower}-{$newUpper}",
                'message' => "Grid adjusted from {$oldLower}-{$oldUpper} to {$newLower}-{$newUpper}",
            ];
        } catch (\Exception $e) {
            Log::error('AgentToolkit: adjust_grid failed', ['error' => $e->getMessage()]);
            $this->logAction($bot, 'grid_adjusted', [
                'reason' => $reason,
                'old_range' => "{$oldLower}-{$oldUpper}",
                'new_range' => "{$newLower}-{$newUpper}",
            ], $beforeState, null, BotActivityLogger::RESULT_FAILED, $e->getMessage());
            return ['error' => 'Failed to adjust grid: ' . $e->getMessage()];
        }
    }

    private function toolCancelAllOrders(Bot $bot): array
    {
        $account = $bot->binanceAccount;
        if (!$account) {
            return ['error' => 'No Binance account'];
        }

        $openCount = $bot->orders()->where('status', OrderStatus::Open)->count();
        $this->binanceFutures->cancelAllOrders($account, $bot->symbol);
        $bot->orders()->where('status', OrderStatus::Open)->update(['status' => OrderStatus::Cancelled]);

        $this->logAction($bot, 'orders_cancelled', [
            'reason' => 'agent_decision',
            'cancelled_count' => $openCount,
        ]);

        return ['success' => true, 'cancelled_count' => $openCount];
    }

    private function toolStopBot(Bot $bot, array $args): array
    {
        $reason = $args['reason'] ?? 'Agent decision';

        // Block stop_bot for all automatic triggers (scheduled + sl_tp_alert).
        // Only manual user-initiated consultations may stop the bot.
        // Rationale: auto-stopping creates a permanent outage — the agent only monitors active
        // bots, so stopping the bot also stops monitoring with no automatic recovery.
        // For SL/TP alerts the intent is repair (adjust_grid + new SL/TP), not shutdown.
        if (in_array($this->trigger, [AgentTrigger::Scheduled, AgentTrigger::SlTpAlert, AgentTrigger::PriceBreakout])) {
            $this->logAction($bot, 'bot_stop_blocked', [
                'reason' => $reason,
                'trigger' => $this->trigger->value,
            ], null, null, BotActivityLogger::RESULT_BLOCKED);

            return [
                'blocked' => true,
                'message' => "stop_bot is disabled for trigger '{$this->trigger->value}'. Only manual consultations can stop the bot. Auto-stopping creates a permanent outage — the agent only runs on active bots.",
                'action_required' => 'Repair instead: (1) close_position to reduce exposure, (2) adjust_grid to recenter around current price, (3) set new SL/TP values that are NOT already breached.',
            ];
        }

        $this->logAction($bot, 'bot_stopped', ['reason' => $reason]);
        $this->engine->stopBot($bot);

        return ['success' => true, 'message' => "Bot stopped: {$reason}"];
    }

    private function toolClosePosition(Bot $bot): array
    {
        $account = $bot->binanceAccount;
        if (!$account) {
            return ['error' => 'No Binance account'];
        }

        $positions = $this->binanceApi->getPositions($account, $bot->symbol);
        if (empty($positions) || (float) ($positions[0]['positionAmt'] ?? 0) == 0) {
            return ['success' => false, 'message' => 'No open position to close'];
        }

        $pos = $positions[0];
        $posAmt = (float) $pos['positionAmt'];
        $side = $posAmt > 0 ? BinanceConstants::SIDE_SELL : BinanceConstants::SIDE_BUY;
        $qty = $this->binanceFutures->formatQuantity($bot->symbol, abs($posAmt));

        $this->logAction($bot, 'position_closed', [
            'reason' => 'agent_decision',
            'size' => $posAmt,
            'close_side' => $side,
            'unrealized_pnl' => $pos['unRealizedProfit'] ?? 0,
        ]);

        $this->binanceFutures->placeMarketOrder($account, $bot->symbol, $side, $qty);

        return ['success' => true, 'closed_size' => $posAmt, 'close_side' => $side];
    }

    private function logAction(
        Bot $bot,
        string $action,
        array $details = [],
        ?array $beforeState = null,
        ?array $afterState = null,
        string $result = BotActivityLogger::RESULT_SUCCESS,
        ?string $errorMessage = null,
    ): void {
        BotActivityLogger::logAgentAction(
            $bot, $action, $details, $this->conversationId,
            $beforeState, $afterState, $result, $errorMessage,
        );
    }
}
