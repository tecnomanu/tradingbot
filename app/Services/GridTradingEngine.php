<?php

namespace App\Services;

use App\Enums\BotSide;
use App\Enums\BotStatus;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Bot;
use App\Models\Order;
use App\Repositories\BotRepository;
use App\Repositories\OrderRepository;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Support\BotLog as Log;

class GridTradingEngine
{
    public function __construct(
        private BinanceFuturesService $binance,
        private BotRepository $botRepository,
        private OrderRepository $orderRepository,
        private GridCalculatorService $gridCalculator,
    ) {}

    /**
     * Initialize the bot on Binance: set leverage, margin type, and place initial grid orders.
     *
     * @throws Exception
     */
    public function initializeBot(Bot $bot): void
    {
        $account = $bot->binanceAccount;

        if (!$account || !$account->is_active) {
            throw new Exception('Binance account is not active');
        }

        Log::info('GridEngine: initializing bot', ['bot_id' => $bot->id, 'symbol' => $bot->symbol]);

        $this->binance->setMarginType($account, $bot->symbol, 'CROSSED');
        $this->binance->setLeverage($account, $bot->symbol, $bot->leverage);

        $currentPrice = $this->binance->getCurrentPrice($account, $bot->symbol);

        if (!$currentPrice) {
            throw new Exception("Cannot get current price for {$bot->symbol}");
        }

        $hasOpenOrders = $bot->orders()->where('status', OrderStatus::Open)->exists();

        if ($hasOpenOrders) {
            $this->placeInitialOrders($bot, $currentPrice);
        } else {
            Log::info('GridEngine: no open orders, rebuilding grid from scratch', ['bot_id' => $bot->id]);
            $this->rebuildGrid($bot, $currentPrice);
        }

        $this->botRepository->update($bot, [
            'status' => BotStatus::Active,
            'started_at' => now(),
        ]);

        Log::info('GridEngine: bot initialized', ['bot_id' => $bot->id]);
    }

    /**
     * Place initial grid limit orders around the current price.
     * Buy orders below current price, sell orders above.
     */
    private function placeInitialOrders(Bot $bot, float $currentPrice): void
    {
        $account = $bot->binanceAccount;
        $orders = $bot->orders()->where('status', OrderStatus::Open)->orderBy('price')->get();

        if ($orders->isEmpty()) {
            Log::warning('GridEngine: no grid orders found for bot', ['bot_id' => $bot->id]);
            return;
        }

        $placedCount = 0;

        foreach ($orders as $order) {
            $isBuyOrder = $order->price < $currentPrice;
            $expectedSide = $isBuyOrder ? OrderSide::Buy : OrderSide::Sell;
            $binanceSide = $isBuyOrder ? 'BUY' : 'SELL';

            if ($order->side !== $expectedSide) {
                $order->update(['side' => $expectedSide]);
            }

            $quantity = $this->binance->formatQuantity($bot->symbol, $order->quantity);
            $price = $this->binance->formatPrice($bot->symbol, $order->price);

            if ($quantity <= 0) {
                Log::warning('GridEngine: skipping order with zero quantity', [
                    'order_id' => $order->id,
                    'grid_level' => $order->grid_level,
                ]);
                continue;
            }

            try {
                $clientOrderId = "grid_{$bot->id}_{$order->grid_level}";

                $result = $this->binance->placeLimitOrder(
                    $account,
                    $bot->symbol,
                    $binanceSide,
                    $quantity,
                    $price,
                    $clientOrderId,
                );

                $order->update([
                    'binance_order_id' => (string) $result['orderId'],
                    'status' => OrderStatus::Open,
                    'quantity' => $quantity,
                    'price' => $price,
                ]);

                $placedCount++;
            } catch (Exception $e) {
                Log::error('GridEngine: failed to place order', [
                    'bot_id' => $bot->id,
                    'grid_level' => $order->grid_level,
                    'price' => $price,
                    'side' => $binanceSide,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GridEngine: placed initial orders', [
            'bot_id' => $bot->id,
            'placed' => $placedCount,
            'total' => $orders->count(),
        ]);
    }

    /**
     * Process an active bot: sync order statuses from Binance, handle fills, rotate grid.
     * Also checks stop-loss and take-profit conditions.
     *
     * Concurrency: ProcessActiveBotJob uses ShouldBeUnique with uniqueId per bot,
     * so only one processBot run per bot can execute at a time. No additional locking needed here.
     */
    public function processBot(Bot $bot): void
    {
        if ($bot->status !== BotStatus::Active) {
            return;
        }

        $account = $bot->binanceAccount;

        if (!$account) {
            $this->botRepository->update($bot, [
                'status' => BotStatus::Error,
                'last_error_message' => 'No Binance account linked',
            ]);
            return;
        }

        try {
            $this->syncOrderStatuses($bot);
            $this->handleFilledOrders($bot);
            $this->autoRebuildIfEmpty($bot);
            $this->updateBotStats($bot);
            $this->checkStopConditions($bot);
        } catch (Exception $e) {
            Log::error('GridEngine: error processing bot', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);

            $this->botRepository->update($bot, [
                'status' => BotStatus::Error,
                'last_error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * When an active bot has zero open orders (all filled or at grid edges),
     * automatically rebuild the grid centered on the current price.
     */
    private function autoRebuildIfEmpty(Bot $bot): void
    {
        $openCount = $bot->orders()->where('status', OrderStatus::Open)->count();

        if ($openCount > 0) {
            return;
        }

        $hasFilled = $bot->orders()->where('status', OrderStatus::Filled)->exists();
        if (!$hasFilled) {
            return;
        }

        $account = $bot->binanceAccount;
        $currentPrice = $this->binance->getCurrentPrice($account, $bot->symbol);

        if (!$currentPrice) {
            Log::warning('GridEngine: cannot auto-rebuild, no price', ['bot_id' => $bot->id]);
            return;
        }

        $oldLower = (float) $bot->price_lower;
        $oldUpper = (float) $bot->price_upper;
        $range = $oldUpper - $oldLower;

        // Center range based on bot side: Long = more room above (40%), Short = more room below (60%), Neutral = 50%
        $side = $bot->side;
        if ($side === BotSide::Long) {
            $newLower = $currentPrice - 0.4 * $range;
            $newUpper = $currentPrice + 0.6 * $range;
        } elseif ($side === BotSide::Short) {
            $newLower = $currentPrice - 0.6 * $range;
            $newUpper = $currentPrice + 0.4 * $range;
        } else {
            $newLower = $currentPrice - 0.5 * $range;
            $newUpper = $currentPrice + 0.5 * $range;
        }

        $newLower = $this->binance->formatPrice($bot->symbol, $newLower);
        $newUpper = $this->binance->formatPrice($bot->symbol, $newUpper);

        Log::info('GridEngine: auto-rebuilding grid (0 open orders)', [
            'bot_id' => $bot->id,
            'reason' => 'all_orders_filled',
            'current_price' => $currentPrice,
            'old_range' => "{$oldLower}-{$oldUpper}",
            'new_range' => "{$newLower}-{$newUpper}",
            'side' => $side->value,
        ]);

        $this->botRepository->update($bot, [
            'price_lower' => $newLower,
            'price_upper' => $newUpper,
        ]);

        $bot->refresh();
        $placedCount = $this->rebuildGrid($bot, $currentPrice);

        Log::info('GridEngine: auto-rebuild complete', [
            'bot_id' => $bot->id,
            'new_range' => "{$newLower}-{$newUpper}",
            'orders_placed' => $placedCount,
        ]);
    }

    /**
     * Check stop-loss and take-profit price conditions.
     * If current price crosses SL or TP, stop the bot and close position.
     */
    private function checkStopConditions(Bot $bot): void
    {
        if (!$bot->stop_loss_price && !$bot->take_profit_price) {
            return;
        }

        $account = $bot->binanceAccount;
        $currentPrice = $this->binance->getCurrentPrice($account, $bot->symbol);

        if (!$currentPrice) {
            return;
        }

        $triggered = null;

        if ($bot->stop_loss_price && $currentPrice <= (float) $bot->stop_loss_price) {
            $triggered = 'stop_loss';
        } elseif ($bot->take_profit_price && $currentPrice >= (float) $bot->take_profit_price) {
            $triggered = 'take_profit';
        }

        if ($triggered) {
            Log::warning("GridEngine: {$triggered} triggered", [
                'bot_id' => $bot->id,
                'current_price' => $currentPrice,
                'sl' => $bot->stop_loss_price,
                'tp' => $bot->take_profit_price,
            ]);

            $this->stopBot($bot);

            $positions = $this->binance->getPositions($account, $bot->symbol);
            foreach ($positions as $pos) {
                if (abs($pos['positionAmt']) > 0) {
                    $this->closePosition($account, $bot->symbol, $pos['positionAmt']);
                }
            }
        }
    }

    /**
     * Close a position with a market order.
     */
    private function closePosition($account, string $symbol, float $positionAmt): void
    {
        try {
            $side = $positionAmt > 0 ? 'SELL' : 'BUY';
            $qty = abs($positionAmt);
            $formattedQty = $this->binance->formatQuantity($symbol, $qty);

            $client = $this->binance->createClient($account);
            $request = new \Binance\Client\DerivativesTradingUsdsFutures\Model\NewOrderRequest([
                'symbol' => $symbol,
                'side' => $side === 'BUY'
                    ? \Binance\Client\DerivativesTradingUsdsFutures\Model\Side::BUY
                    : \Binance\Client\DerivativesTradingUsdsFutures\Model\Side::SELL,
                'type' => 'MARKET',
                'quantity' => $formattedQty,
                'reduceOnly' => true,
            ]);
            $client->newOrder($request);

            Log::info('GridEngine: position closed', [
                'symbol' => $symbol,
                'qty' => $formattedQty,
                'side' => $side,
            ]);
        } catch (Exception $e) {
            Log::error('GridEngine: failed to close position', [
                'symbol' => $symbol,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sync local order statuses with Binance.
     * Compares local open orders against Binance's open order list.
     * Orders missing from Binance open list are individually queried.
     */
    private function syncOrderStatuses(Bot $bot): void
    {
        $account = $bot->binanceAccount;

        $openOrders = $bot->orders()
            ->where('status', OrderStatus::Open)
            ->whereNotNull('binance_order_id')
            ->get();

        if ($openOrders->isEmpty()) {
            return;
        }

        $binanceOpenIds = collect($this->binance->getOpenOrders($account, $bot->symbol))
            ->pluck('orderId')
            ->map(fn($id) => (string) $id)
            ->toArray();

        foreach ($openOrders as $order) {
            if (in_array($order->binance_order_id, $binanceOpenIds, true)) {
                continue;
            }

            $orderInfo = $this->binance->queryOrder(
                $account,
                $bot->symbol,
                (int) $order->binance_order_id,
            );

            if (!$orderInfo) {
                // Order not found on Binance (ghost order) - mark as Cancelled
                $order->update(['status' => OrderStatus::Cancelled]);
                Log::info('GridEngine: ghost order marked cancelled (not found on Binance)', [
                    'bot_id' => $bot->id,
                    'order_id' => $order->id,
                    'binance_order_id' => $order->binance_order_id,
                ]);
                continue;
            }

            $status = strtoupper($orderInfo['status']);

            if ($status === 'FILLED') {
                $filledQty = (float) ($orderInfo['executedQty'] ?? $order->quantity);
                $avgPrice = (float) ($orderInfo['avgPrice'] ?? $order->price);

                $order->update([
                    'status' => OrderStatus::Filled,
                    'filled_at' => now(),
                    'quantity' => $filledQty,
                    'price' => $avgPrice ?: $order->price,
                ]);

                Log::info('GridEngine: order filled', [
                    'bot_id' => $bot->id,
                    'order_id' => $order->id,
                    'side' => $order->side->value,
                    'level' => $order->grid_level,
                    'qty' => $filledQty,
                    'price' => $avgPrice,
                ]);
            } elseif (in_array($status, ['CANCELED', 'CANCELLED', 'EXPIRED'])) {
                $order->update(['status' => OrderStatus::Cancelled]);
            } elseif ($status === 'PARTIALLY_FILLED') {
                $order->update(['status' => OrderStatus::PartiallyFilled]);
            }
        }
    }

    /**
     * Handle filled orders: calculate PNL and place counter-orders to continue the grid.
     *
     * Grid logic:
     * - When a BUY order fills → place a SELL order one grid level up
     * - When a SELL order fills → place a BUY order one grid level down
     * - Never duplicate: skip if there's already an open order at the target level+side
     */
    private function handleFilledOrders(Bot $bot): void
    {
        $filledOrders = $bot->orders()
            ->where('status', OrderStatus::Filled)
            ->whereNull('pnl')
            ->get();

        if ($filledOrders->isEmpty()) {
            return;
        }

        $account = $bot->binanceAccount;
        $gridConfig = $this->gridCalculator->calculateFullGridConfig([
            'price_lower' => $bot->price_lower,
            'price_upper' => $bot->price_upper,
            'grid_count' => $bot->grid_count,
            'investment' => $bot->investment,
            'leverage' => $bot->leverage,
            'side' => $bot->side->value,
        ]);

        $gridLevels = $gridConfig['grid_levels'];
        $quantityPerGrid = $this->binance->formatQuantity(
            $bot->symbol,
            $gridConfig['quantity_per_grid'],
        );
        $gridStep = ($bot->price_upper - $bot->price_lower) / $bot->grid_count;

        foreach ($filledOrders as $order) {
            $pnl = 0;
            $counterSide = null;
            $counterLevel = null;

            if ($order->side === OrderSide::Buy) {
                $counterLevel = $order->grid_level + 1;
                $counterSide = OrderSide::Sell;
                $pnl = 0;
            } elseif ($order->side === OrderSide::Sell) {
                $counterLevel = $order->grid_level - 1;
                $counterSide = OrderSide::Buy;

                $realQty = $order->quantity ?: $quantityPerGrid;
                $commission = $order->price * $realQty * 0.0004 * 2;
                $pnl = round($gridStep * $realQty - $commission, 4);
            }

            if ($counterLevel === null || !isset($gridLevels[$counterLevel])) {
                $order->update(['pnl' => $pnl]);
                Log::info('GridEngine: no counter level available (edge of grid)', [
                    'bot_id' => $bot->id,
                    'filled_level' => $order->grid_level,
                    'counter_level' => $counterLevel,
                ]);
                continue;
            }

            $existingOpen = $bot->orders()
                ->where('grid_level', $counterLevel)
                ->where('side', $counterSide)
                ->where('status', OrderStatus::Open)
                ->exists();

            if ($existingOpen) {
                $order->update(['pnl' => $pnl]);
                Log::info('GridEngine: counter-order skipped (already exists)', [
                    'bot_id' => $bot->id,
                    'counter_level' => $counterLevel,
                    'side' => $counterSide->value,
                ]);
                continue;
            }

            $counterPrice = $gridLevels[$counterLevel];
            $placed = $this->placeCounterOrder($bot, $account, $counterSide, $counterLevel, $counterPrice, $quantityPerGrid);

            if ($placed) {
                $order->update(['pnl' => $pnl]);
            } else {
                // Still set pnl so we don't retry forever; autoRebuildIfEmpty will handle grid rebuild
                $order->update(['pnl' => $pnl]);
                Log::warning('GridEngine: counter-order failed, will retry next cycle', [
                    'bot_id' => $bot->id,
                    'filled_order_id' => $order->id,
                    'counter_level' => $counterLevel,
                ]);
            }
        }
    }

    /**
     * Place a counter-order on the opposite side of a filled grid order.
     */
    private function placeCounterOrder(
        Bot $bot,
        $account,
        OrderSide $side,
        int $gridLevel,
        float $price,
        float $quantity,
    ): bool {
        $binanceSide = $side === OrderSide::Buy ? 'BUY' : 'SELL';
        $formattedQty = $this->binance->formatQuantity($bot->symbol, $quantity);
        $formattedPrice = $this->binance->formatPrice($bot->symbol, $price);

        if ($formattedQty <= 0) {
            return false;
        }

        try {
            $clientOrderId = "grid_{$bot->id}_{$gridLevel}_" . time();

            $result = $this->binance->placeLimitOrder(
                $account,
                $bot->symbol,
                $binanceSide,
                $formattedQty,
                $formattedPrice,
                $clientOrderId,
            );

            Order::create([
                'bot_id' => $bot->id,
                'side' => $side,
                'status' => OrderStatus::Open,
                'price' => $formattedPrice,
                'quantity' => $formattedQty,
                'grid_level' => $gridLevel,
                'binance_order_id' => (string) $result['orderId'],
            ]);

            Log::info('GridEngine: counter-order placed', [
                'bot_id' => $bot->id,
                'side' => $binanceSide,
                'grid_level' => $gridLevel,
                'price' => $formattedPrice,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('GridEngine: failed to place counter-order', [
                'bot_id' => $bot->id,
                'grid_level' => $gridLevel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update bot aggregate stats: total PNL, grid profit, rounds.
     */
    private function updateBotStats(Bot $bot): void
    {
        $filledOrders = $bot->orders()->where('status', OrderStatus::Filled)->get();

        $totalPnl = $filledOrders->sum('pnl');
        $sellFills = $filledOrders->where('side', OrderSide::Sell)->count();
        $buyFills = $filledOrders->where('side', OrderSide::Buy)->count();
        $rounds = min($sellFills, $buyFills);

        $account = $bot->binanceAccount;
        $trendPnl = 0;

        if ($account) {
            $positions = $this->binance->getPositions($account, $bot->symbol);
            foreach ($positions as $pos) {
                $trendPnl += $pos['unrealizedProfit'];
            }
        }

        $rounds24h = $bot->orders()
            ->where('status', OrderStatus::Filled)
            ->where('side', OrderSide::Sell)
            ->where('filled_at', '>=', now()->subDay())
            ->count();

        $this->botRepository->update($bot, [
            'total_pnl' => round($totalPnl + $trendPnl, 4),
            'grid_profit' => round($totalPnl, 4),
            'trend_pnl' => round($trendPnl, 4),
            'total_rounds' => $rounds,
            'rounds_24h' => $rounds24h,
        ]);
    }

    /**
     * Gracefully stop a bot: cancel all open orders on Binance.
     */
    public function stopBot(Bot $bot): void
    {
        $account = $bot->binanceAccount;

        if ($account) {
            $this->binance->cancelAllOrders($account, $bot->symbol);
        }

        // Mark all open local orders as cancelled
        $bot->orders()
            ->where('status', OrderStatus::Open)
            ->update(['status' => OrderStatus::Cancelled->value]);

        $this->botRepository->update($bot, [
            'status' => BotStatus::Stopped,
            'stopped_at' => now(),
        ]);

        Log::info('GridEngine: bot stopped', ['bot_id' => $bot->id]);
    }

    /**
     * Rebuild the entire grid from the bot's existing config.
     * Used when restarting a stopped bot that has no Open orders.
     *
     * @return int Number of orders successfully placed on Binance
     */
    private function rebuildGrid(Bot $bot, float $currentPrice): int
    {
        $account = $bot->binanceAccount;

        // Prevent duplicate orders: cancel/delete any open orders not yet placed on Binance
        $bot->orders()
            ->where('status', OrderStatus::Open)
            ->whereNull('binance_order_id')
            ->delete();

        $gridConfig = $this->gridCalculator->calculateFullGridConfig([
            'price_lower' => $bot->price_lower,
            'price_upper' => $bot->price_upper,
            'grid_count' => $bot->grid_count,
            'investment' => $bot->investment,
            'leverage' => $bot->leverage,
            'side' => $bot->side->value,
        ]);

        $gridLevels = $gridConfig['grid_levels'];
        $quantity = $gridConfig['quantity_per_grid'];
        $now = now();
        $orders = [];

        foreach ($gridLevels as $level => $price) {
            $side = $price < $currentPrice ? OrderSide::Buy : OrderSide::Sell;
            $orders[] = [
                'bot_id' => $bot->id,
                'side' => $side->value,
                'status' => OrderStatus::Open->value,
                'price' => $price,
                'quantity' => $quantity,
                'grid_level' => $level,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->orderRepository->createMany($orders);

        $newOrders = $bot->orders()
            ->where('status', OrderStatus::Open)
            ->where('created_at', '>=', $now)
            ->orderBy('price')
            ->get();

        $placedCount = 0;

        foreach ($newOrders as $order) {
            $binanceSide = $order->side === OrderSide::Buy ? 'BUY' : 'SELL';
            $qty = $this->binance->formatQuantity($bot->symbol, $order->quantity);
            $formattedPrice = $this->binance->formatPrice($bot->symbol, $order->price);

            if ($qty <= 0) {
                continue;
            }

            try {
                $clientOrderId = "grid_{$bot->id}_{$order->grid_level}";
                $result = $this->binance->placeLimitOrder(
                    $account, $bot->symbol, $binanceSide, $qty, $formattedPrice, $clientOrderId
                );

                $order->update([
                    'binance_order_id' => (string) $result['orderId'],
                    'status' => OrderStatus::Open,
                    'quantity' => $qty,
                    'price' => $formattedPrice,
                ]);
                $placedCount++;
            } catch (Exception $e) {
                Log::error('GridEngine: failed to place rebuilt grid order', [
                    'bot_id' => $bot->id,
                    'grid_level' => $order->grid_level,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GridEngine: grid rebuilt', [
            'bot_id' => $bot->id,
            'range' => "{$bot->price_lower}-{$bot->price_upper}",
            'placed' => $placedCount,
            'total' => $newOrders->count(),
        ]);

        return $placedCount;
    }

    /**
     * Reinitialize grid orders for an active bot after range adjustment.
     * Recalculates grid levels, creates new DB orders, and places them on Binance.
     */
    public function reinitializeGrid(Bot $bot): void
    {
        $account = $bot->binanceAccount;
        if (!$account) {
            throw new Exception('No Binance account linked');
        }

        $gridConfig = $this->gridCalculator->calculateFullGridConfig([
            'price_lower' => $bot->price_lower,
            'price_upper' => $bot->price_upper,
            'grid_count' => $bot->grid_count,
            'investment' => $bot->investment,
            'leverage' => $bot->leverage,
            'side' => $bot->side->value,
        ]);

        $bot->update([
            'profit_per_grid' => $gridConfig['profit_per_grid'],
            'commission_per_grid' => $gridConfig['commission_per_grid'],
        ]);

        $now = now();
        $gridLevels = $gridConfig['grid_levels'];
        $quantity = $gridConfig['quantity_per_grid'];
        $orders = [];

        $currentPrice = $this->binance->getCurrentPrice($account, $bot->symbol);
        if (!$currentPrice) {
            throw new Exception("Cannot get current price for {$bot->symbol}");
        }

        foreach ($gridLevels as $level => $price) {
            $side = $price < $currentPrice ? OrderSide::Buy : OrderSide::Sell;
            $orders[] = [
                'bot_id' => $bot->id,
                'side' => $side->value,
                'status' => OrderStatus::Open->value,
                'price' => $price,
                'quantity' => $quantity,
                'grid_level' => $level,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->orderRepository->createMany($orders);

        $newOrders = $bot->orders()->where('status', OrderStatus::Open)->where('created_at', '>=', $now)->orderBy('price')->get();
        $placedCount = 0;

        foreach ($newOrders as $order) {
            $binanceSide = $order->side === OrderSide::Buy ? 'BUY' : 'SELL';
            $qty = $this->binance->formatQuantity($bot->symbol, $order->quantity);
            $formattedPrice = $this->binance->formatPrice($bot->symbol, $order->price);

            if ($qty <= 0) {
                continue;
            }

            try {
                $clientOrderId = "grid_{$bot->id}_{$order->grid_level}";
                $result = $this->binance->placeLimitOrder($account, $bot->symbol, $binanceSide, $qty, $formattedPrice, $clientOrderId);

                $order->update([
                    'binance_order_id' => (string) $result['orderId'],
                    'status' => OrderStatus::Open,
                    'quantity' => $qty,
                    'price' => $formattedPrice,
                ]);
                $placedCount++;
            } catch (Exception $e) {
                Log::error('GridEngine: failed to place adjusted grid order', [
                    'bot_id' => $bot->id,
                    'grid_level' => $order->grid_level,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('GridEngine: grid reinitialized', [
            'bot_id' => $bot->id,
            'range' => "{$bot->price_lower}-{$bot->price_upper}",
            'placed' => $placedCount,
            'total' => $newOrders->count(),
        ]);
    }
}
