<?php

namespace App\Services;

use App\Enums\BotStatus;
use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Jobs\InitializeBotJob;
use App\Models\Bot;
use App\Repositories\BotRepository;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;

class BotService
{
    public function __construct(
        private BotRepository $botRepository,
        private OrderRepository $orderRepository,
        private GridCalculatorService $gridCalculator,
        private GridTradingEngine $gridEngine,
    ) {}

    /**
     * Create a new bot with its grid orders (DB only, not yet placed on Binance).
     */
    public function createBot(array $data): Bot
    {
        return DB::transaction(function () use ($data) {
            $gridConfig = $this->gridCalculator->calculateFullGridConfig($data);

            $botData = array_merge($data, [
                'real_investment' => $gridConfig['real_investment'],
                'additional_margin' => $gridConfig['additional_margin'],
                'est_liquidation_price' => $gridConfig['est_liquidation_price'],
                'profit_per_grid' => $gridConfig['profit_per_grid'],
                'commission_per_grid' => $gridConfig['commission_per_grid'],
                'status' => BotStatus::Pending,
                'ai_system_prompt' => $data['ai_system_prompt'] ?? \App\Services\Agent\AgentOrchestrator::defaultPersonality(),
                'ai_user_prompt' => $data['ai_user_prompt'] ?? \App\Services\Agent\AgentOrchestrator::defaultUserPrompt(),
            ]);

            $bot = $this->botRepository->create($botData);

            $this->createGridOrders($bot, $gridConfig);

            return $bot->load('orders');
        });
    }

    /**
     * Start a bot: dispatch the initialization job that places orders on Binance.
     */
    public function startBot(Bot $bot): Bot
    {
        if ($bot->status === BotStatus::Active) {
            return $bot;
        }

        InitializeBotJob::dispatch($bot);

        return $bot;
    }

    /**
     * Stop a bot: cancel all Binance orders and update status.
     */
    public function stopBot(Bot $bot): Bot
    {
        $this->gridEngine->stopBot($bot);

        return $bot->fresh();
    }

    /**
     * Delete a bot: stop it first if active, then delete from DB.
     */
    public function deleteBot(Bot $bot): void
    {
        if ($bot->status === BotStatus::Active) {
            $this->gridEngine->stopBot($bot);
        }

        $this->orderRepository->deleteByBot($bot->id);
        $this->botRepository->delete($bot);
    }

    /**
     * Get a detailed summary of a bot.
     */
    public function getBotSummary(Bot $bot): array
    {
        $orderStats = $this->orderRepository->getBotOrderStats($bot->id);
        $bot->load('binanceAccount:id,label');

        return [
            'bot' => $bot,
            'order_stats' => $orderStats,
            'grid_config' => $this->gridCalculator->calculateFullGridConfig([
                'price_lower' => $bot->price_lower,
                'price_upper' => $bot->price_upper,
                'grid_count' => $bot->grid_count,
                'investment' => $bot->investment,
                'leverage' => $bot->leverage,
                'side' => $bot->side->value,
            ]),
        ];
    }

    /**
     * Create grid orders (buy/sell) for a bot in DB.
     */
    private function createGridOrders(Bot $bot, array $gridConfig): void
    {
        $orders = [];
        $now = now();
        $gridLevels = $gridConfig['grid_levels'];
        $quantity = $gridConfig['quantity_per_grid'];

        foreach ($gridLevels as $level => $price) {
            $midLevel = count($gridLevels) / 2;
            $side = $level < $midLevel ? OrderSide::Buy : OrderSide::Sell;

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
    }
}
