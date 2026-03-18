<?php

namespace Database\Factories;

use App\Enums\BotSide;
use App\Enums\BotStatus;
use App\Models\BinanceAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bot>
 */
class BotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'            => User::factory(),
            'binance_account_id' => BinanceAccount::factory(),
            'name'               => 'Bot ' . fake()->word(),
            'symbol'             => 'BTCUSDT',
            'side'               => BotSide::Long->value,
            'status'             => BotStatus::Pending->value,
            'price_lower'        => 80000.0,
            'price_upper'        => 100000.0,
            'grid_count'         => 10,
            'grid_mode'          => 'arithmetic',
            'investment'         => 100.0,
            'leverage'           => 1,
            'slippage'           => 0.1,
            'real_investment'    => 100.0,
            'additional_margin'  => 0.0,
            'est_liquidation_price' => 0.0,
            'profit_per_grid'    => 0.12,
            'commission_per_grid' => 0.08,
            'total_pnl'          => 0.0,
            'grid_profit'        => 0.0,
            'trend_pnl'          => 0.0,
            'total_rounds'       => 0,
            'rounds_24h'         => 0,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'status'     => BotStatus::Active->value,
            'started_at' => now(),
        ]);
    }

    public function stopped(): static
    {
        return $this->state([
            'status'     => BotStatus::Stopped->value,
            'stopped_at' => now(),
        ]);
    }

    public function withLeverage(int $leverage): static
    {
        return $this->state([
            'leverage'           => $leverage,
            'real_investment'    => round(100.0 / $leverage, 4),
            'additional_margin'  => round(100.0 - (100.0 / $leverage), 4),
        ]);
    }
}
