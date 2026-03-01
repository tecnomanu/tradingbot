<?php

namespace App\Services\Agent;

use App\Models\Bot;
use App\Models\BotActionLog;
use App\Services\AiTradingAgent;
use App\Services\BinanceApiService;
use App\Services\BinanceFuturesService;
use App\Services\GridTradingEngine;
use App\Support\BotLog as Log;

class AgentToolkit
{
    private ?int $conversationId = null;

    public function __construct(
        private GridTradingEngine $engine,
        private BinanceApiService $binanceApi,
        private BinanceFuturesService $binanceFutures,
        private AiTradingAgent $aiAgent,
    ) {}

    public function setConversationId(int $id): void
    {
        $this->conversationId = $id;
    }

    /**
     * OpenAI-compatible tool definitions for function calling.
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_market_data',
                    'description' => 'Get current market technical indicators: price, RSI, SMA, MACD, Bollinger Bands, ATR, volume ratio. Use this to analyze market conditions.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'symbol' => ['type' => 'string', 'description' => 'Trading pair symbol, e.g. BTCUSDT'],
                        ],
                        'required' => ['symbol'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_bot_status',
                    'description' => 'Get full bot status: config, PNL, grid range, open/filled orders count, SL/TP settings, investment, rounds.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                        ],
                        'required' => ['bot_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_open_orders',
                    'description' => 'List all open orders for a bot with price, side, quantity and grid level.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                        ],
                        'required' => ['bot_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_filled_orders',
                    'description' => 'List recently filled orders with PNL, fill time. Shows trading activity.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                            'limit' => ['type' => 'integer', 'description' => 'Max orders to return (default 20)'],
                        ],
                        'required' => ['bot_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_binance_position',
                    'description' => 'Get the real Binance Futures position for this bot: size, entry price, unrealized PNL, liquidation price.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                        ],
                        'required' => ['bot_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_stop_loss',
                    'description' => 'Set or update the stop-loss price for a bot. The bot will close position and stop if price hits this level.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                            'price' => ['type' => 'number', 'description' => 'Stop-loss price in USDT'],
                        ],
                        'required' => ['bot_id', 'price'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'set_take_profit',
                    'description' => 'Set or update the take-profit price for a bot. The bot will close position and stop if price hits this level.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                            'price' => ['type' => 'number', 'description' => 'Take-profit price in USDT'],
                        ],
                        'required' => ['bot_id', 'price'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'cancel_all_orders',
                    'description' => 'Cancel ALL open orders on Binance for this bot. Use with caution - this removes the entire grid.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                        ],
                        'required' => ['bot_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'stop_bot',
                    'description' => 'Completely stop a bot: cancels all orders, marks bot as stopped. IRREVERSIBLE action - only use if conditions are dangerous.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                            'reason' => ['type' => 'string', 'description' => 'Reason for stopping'],
                        ],
                        'required' => ['bot_id', 'reason'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'close_position',
                    'description' => 'Close the open Binance Futures position for this bot with a market order. Use to realize PNL or cut losses.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'bot_id' => ['type' => 'integer', 'description' => 'Bot ID'],
                        ],
                        'required' => ['bot_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'done',
                    'description' => 'Signal that you have completed your analysis and actions. Provide a summary of findings and any actions taken.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'summary' => ['type' => 'string', 'description' => 'Summary of analysis, findings, and actions taken'],
                        ],
                        'required' => ['summary'],
                    ],
                ],
            ],
        ];
    }

    public function executeTool(string $name, array $args, Bot $bot): array
    {
        try {
            return match ($name) {
                'get_market_data' => $this->toolGetMarketData($args),
                'get_bot_status' => $this->toolGetBotStatus($bot),
                'get_open_orders' => $this->toolGetOpenOrders($bot),
                'get_filled_orders' => $this->toolGetFilledOrders($bot, $args),
                'get_binance_position' => $this->toolGetPosition($bot),
                'set_stop_loss' => $this->toolSetStopLoss($bot, $args),
                'set_take_profit' => $this->toolSetTakeProfit($bot, $args),
                'cancel_all_orders' => $this->toolCancelAllOrders($bot),
                'stop_bot' => $this->toolStopBot($bot, $args),
                'close_position' => $this->toolClosePosition($bot),
                'done' => ['status' => 'done', 'summary' => $args['summary'] ?? ''],
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
            'id' => $bot->id,
            'symbol' => $bot->symbol,
            'status' => $bot->status->value,
            'side' => $bot->side,
            'leverage' => $bot->leverage,
            'price_lower' => (float) $bot->price_lower,
            'price_upper' => (float) $bot->price_upper,
            'grid_count' => $bot->grid_count,
            'investment' => (float) $bot->investment,
            'total_pnl' => (float) $bot->total_pnl,
            'grid_profit' => (float) $bot->grid_profit,
            'total_rounds' => $bot->total_rounds,
            'stop_loss_price' => $bot->stop_loss_price ? (float) $bot->stop_loss_price : null,
            'take_profit_price' => $bot->take_profit_price ? (float) $bot->take_profit_price : null,
            'open_orders' => $bot->orders()->where('status', 'open')->count(),
            'filled_orders' => $bot->orders()->where('status', 'filled')->count(),
            'created_at' => $bot->created_at->toIso8601String(),
            'running_for' => $bot->created_at->diffForHumans(),
        ];
    }

    private function toolGetOpenOrders(Bot $bot): array
    {
        $orders = $bot->orders()
            ->where('status', 'open')
            ->orderBy('price')
            ->get(['id', 'side', 'price', 'quantity', 'grid_level'])
            ->map(fn($o) => [
                'id' => $o->id,
                'side' => $o->side->value,
                'price' => (float) $o->price,
                'quantity' => (float) $o->quantity,
                'grid_level' => $o->grid_level,
            ]);

        return [
            'count' => $orders->count(),
            'buy_orders' => $orders->where('side', 'BUY')->count(),
            'sell_orders' => $orders->where('side', 'SELL')->count(),
            'lowest_buy' => $orders->where('side', 'BUY')->min('price'),
            'highest_sell' => $orders->where('side', 'SELL')->max('price'),
            'orders' => $orders->values()->toArray(),
        ];
    }

    private function toolGetFilledOrders(Bot $bot, array $args): array
    {
        $limit = min($args['limit'] ?? 20, 50);
        $orders = $bot->orders()
            ->where('status', 'filled')
            ->orderByDesc('filled_at')
            ->limit($limit)
            ->get(['id', 'side', 'price', 'quantity', 'pnl', 'grid_level', 'filled_at']);

        $totalPnl = $orders->sum('pnl');
        return [
            'count' => $orders->count(),
            'total_pnl' => round($totalPnl, 4),
            'orders' => $orders->map(fn($o) => [
                'side' => $o->side->value,
                'price' => (float) $o->price,
                'quantity' => (float) $o->quantity,
                'pnl' => (float) $o->pnl,
                'grid_level' => $o->grid_level,
                'filled_at' => $o->filled_at,
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

        $before = $bot->stop_loss_price;
        $bot->update(['stop_loss_price' => $price]);

        $this->logAction($bot, 'sl_set', 'agent', [
            'price' => $price,
            'previous' => $before,
        ]);

        return ['success' => true, 'stop_loss_price' => $price, 'previous' => $before];
    }

    private function toolSetTakeProfit(Bot $bot, array $args): array
    {
        $price = (float) ($args['price'] ?? 0);
        if ($price <= 0) {
            return ['error' => 'Invalid TP price'];
        }

        $before = $bot->take_profit_price;
        $bot->update(['take_profit_price' => $price]);

        $this->logAction($bot, 'tp_set', 'agent', [
            'price' => $price,
            'previous' => $before,
        ]);

        return ['success' => true, 'take_profit_price' => $price, 'previous' => $before];
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
