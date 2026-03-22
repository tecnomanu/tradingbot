<?php

namespace App\Services\Agent;

use App\Constants\BinanceConstants;
use App\Enums\AgentTrigger;
use App\Enums\BotStatus;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Bot;
use App\Services\AiTradingAgent;
use App\Services\BinanceApiService;
use App\Services\BinanceFuturesService;
use App\Services\BotActivityLogger;
use App\Services\BotService;
use App\Services\GridTradingEngine;
use App\Support\BotLog as Log;

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
            $this->tool('get_bot_status', 'Bot config, PNL, grid, SL/TP, orders.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('get_market_data', 'Price, RSI, SMA, MACD, Bollinger, ATR.', [
                'symbol' => ['type' => 'string'],
            ]),
            $this->tool('get_open_orders', 'Open orders summary.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('get_filled_orders', 'Recent fills + total PNL.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('get_binance_position', 'Live position data.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('set_stop_loss', 'Set SL price.', [
                'bot_id' => ['type' => 'integer'],
                'price' => ['type' => 'number'],
            ]),
            $this->tool('set_take_profit', 'Set TP price.', [
                'bot_id' => ['type' => 'integer'],
                'price' => ['type' => 'number'],
            ]),
            $this->tool('cancel_all_orders', 'Cancel all open orders.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('adjust_grid', 'Adjust grid range. Preferred over stop. Provide reason: price_outside_range, volatility_shift, trend_change, protection_mode, bot_recovery.', [
                'bot_id' => ['type' => 'integer'],
                'new_price_lower' => ['type' => 'number'],
                'new_price_upper' => ['type' => 'number'],
                'reason' => ['type' => 'string', 'description' => 'Why: price_outside_range | volatility_shift | trend_change | protection_mode | bot_recovery'],
            ]),
            $this->tool('stop_bot', 'Stop bot. LAST RESORT.', [
                'bot_id' => ['type' => 'integer'],
                'reason' => ['type' => 'string'],
            ]),
            $this->tool('close_position', 'Market-close position.', [
                'bot_id' => ['type' => 'integer'],
            ]),
            $this->tool('done', 'Finish: analysis (Spanish, 3-5 sentences, numbers) + summary (1 sentence).', [
                'analysis' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
            ]),
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
                'set_stop_loss' => $this->toolSetStopLoss($bot, $args),
                'set_take_profit' => $this->toolSetTakeProfit($bot, $args),
                'adjust_grid' => $this->toolAdjustGrid($bot, $args),
                'cancel_all_orders' => $this->toolCancelAllOrders($bot),
                'stop_bot' => $this->toolStopBot($bot, $args),
                'close_position' => $this->toolClosePosition($bot),
                'done' => ['ok' => true, 'analysis' => $args['analysis'] ?? null],
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
        return $data ?: ['error' => 'Could not fetch market data'];
    }

    private function toolGetBotStatus(Bot $bot): array
    {
        $bot->refresh();
        return [
            'status' => $bot->status->value,
            'symbol' => $bot->symbol,
            'side' => $bot->side,
            'leverage' => $bot->leverage,
            'lower' => (float) $bot->price_lower,
            'upper' => (float) $bot->price_upper,
            'grids' => $bot->grid_count,
            'investment' => (float) $bot->investment,
            'pnl' => (float) $bot->total_pnl,
            'grid_profit' => (float) $bot->grid_profit,
            'rounds' => $bot->total_rounds,
            'sl' => $bot->stop_loss_price ? (float) $bot->stop_loss_price : null,
            'tp' => $bot->take_profit_price ? (float) $bot->take_profit_price : null,
            'open_orders' => $bot->orders()->where('status', OrderStatus::Open)->count(),
            'filled_orders' => $bot->orders()->where('status', OrderStatus::Filled)->count(),
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
