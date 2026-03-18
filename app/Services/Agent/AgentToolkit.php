<?php

namespace App\Services\Agent;

use App\Models\Bot;
use App\Models\BotActionLog;
use App\Services\AiTradingAgent;
use App\Services\BinanceApiService;
use App\Services\BinanceFuturesService;
use App\Services\BotService;
use App\Services\GridTradingEngine;
use App\Support\BotLog as Log;

class AgentToolkit
{
    private ?int $conversationId = null;
    private string $trigger = 'scheduled';

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

    public function setTrigger(string $trigger): void
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
            $this->tool('adjust_grid', 'Adjust grid range. Preferred over stop.', [
                'bot_id' => ['type' => 'integer'],
                'new_price_lower' => ['type' => 'number'],
                'new_price_upper' => ['type' => 'number'],
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
        if (in_array($name, self::ACTION_TOOLS) && $bot->status->value === 'stopped') {
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
            'open_orders' => $bot->orders()->where('status', 'open')->count(),
            'filled_orders' => $bot->orders()->where('status', 'filled')->count(),
        ];
    }

    private function toolGetOpenOrders(Bot $bot): array
    {
        $orders = $bot->orders()
            ->where('status', 'open')
            ->orderBy('price')
            ->get(['side', 'price', 'grid_level']);

        $buys = $orders->filter(fn($o) => $o->side->value === 'BUY');
        $sells = $orders->filter(fn($o) => $o->side->value === 'SELL');

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
        $allFilled = $bot->orders()->where('status', 'filled');
        $totalCount = $allFilled->count();
        $totalPnl = round((clone $allFilled)->sum('pnl'), 4);

        $recent = $bot->orders()
            ->where('status', 'filled')
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

        $this->logAction($bot, 'sl_set', 'agent', [
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

        $this->logAction($bot, 'tp_set', 'agent', [
            'price' => $price,
            'previous' => $before ?: null,
        ]);

        return ['success' => true, 'take_profit_price' => $price, 'previous' => $before ?: null];
    }

    private function toolAdjustGrid(Bot $bot, array $args): array
    {
        $newLower = (float) ($args['new_price_lower'] ?? 0);
        $newUpper = (float) ($args['new_price_upper'] ?? 0);

        if ($newLower <= 0 || $newUpper <= 0 || $newLower >= $newUpper) {
            return ['error' => 'Invalid price range: lower must be > 0 and < upper'];
        }

        $oldLower = (float) $bot->price_lower;
        $oldUpper = (float) $bot->price_upper;

        $account = $bot->binanceAccount;
        if (!$account) {
            return ['error' => 'No Binance account linked'];
        }

        try {
            $this->binanceFutures->cancelAllOrders($account, $bot->symbol);
            $bot->orders()->where('status', 'open')->update(['status' => 'cancelled']);

            $bot->update([
                'price_lower' => $newLower,
                'price_upper' => $newUpper,
            ]);

            $this->engine->reinitializeGrid($bot->fresh());

            $this->logAction($bot, 'grid_adjusted', 'agent', [
                'old_range' => "{$oldLower}-{$oldUpper}",
                'new_range' => "{$newLower}-{$newUpper}",
            ]);

            return [
                'success' => true,
                'old_range' => "{$oldLower}-{$oldUpper}",
                'new_range' => "{$newLower}-{$newUpper}",
                'message' => "Grid adjusted from {$oldLower}-{$oldUpper} to {$newLower}-{$newUpper}",
            ];
        } catch (\Exception $e) {
            Log::error('AgentToolkit: adjust_grid failed', ['error' => $e->getMessage()]);
            return ['error' => 'Failed to adjust grid: ' . $e->getMessage()];
        }
    }

    private function toolCancelAllOrders(Bot $bot): array
    {
        $account = $bot->binanceAccount;
        if (!$account) {
            return ['error' => 'No Binance account'];
        }

        $openCount = $bot->orders()->where('status', 'open')->count();
        $this->binanceFutures->cancelAllOrders($account, $bot->symbol);
        $bot->orders()->where('status', 'open')->update(['status' => 'cancelled']);

        $this->logAction($bot, 'orders_cancelled', 'agent', [
            'cancelled_count' => $openCount,
        ]);

        return ['success' => true, 'cancelled_count' => $openCount];
    }

    private function toolStopBot(Bot $bot, array $args): array
    {
        $reason = $args['reason'] ?? 'Agent decision';

        // In scheduled (routine) runs, blocking stop_bot prevents the self-defeating loop:
        // bot stops → agent stops running → bot stays stopped forever with no recovery.
        // Manual consultations and alert consultations (sl_tp_alert) are allowed to stop.
        if ($this->trigger === 'scheduled') {
            $this->logAction($bot, 'bot_stop_blocked', 'agent', [
                'reason' => $reason,
                'trigger' => 'scheduled',
            ]);

            return [
                'blocked' => true,
                'message' => 'stop_bot is disabled in scheduled mode. Auto-stopping creates a permanent outage because the agent only runs on active bots — if the bot stops, the agent stops too and the bot stays stopped forever.',
                'action_required' => 'To protect capital without stopping: (1) call close_position to reduce exposure, (2) call adjust_grid to recenter the grid at a safer price range. The situation has been flagged in the activity log.',
            ];
        }

        $this->logAction($bot, 'bot_stopped', 'agent', ['reason' => $reason]);
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
        $side = $posAmt > 0 ? 'SELL' : 'BUY';
        $qty = $this->binanceFutures->formatQuantity($bot->symbol, abs($posAmt));

        $this->logAction($bot, 'position_closed', 'agent', [
            'size' => $posAmt,
            'close_side' => $side,
            'unrealized_pnl' => $pos['unRealizedProfit'] ?? 0,
        ]);

        $this->binanceFutures->placeMarketOrder($account, $bot->symbol, $side, $qty);

        return ['success' => true, 'closed_size' => $posAmt, 'close_side' => $side];
    }

    private function logAction(Bot $bot, string $action, string $source, array $details = []): void
    {
        BotActionLog::create([
            'bot_id' => $bot->id,
            'conversation_id' => $this->conversationId,
            'action' => $action,
            'source' => $source,
            'details' => $details,
        ]);
    }
}
