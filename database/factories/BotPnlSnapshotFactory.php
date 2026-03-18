<?php

namespace Database\Factories;

use App\Models\Bot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BotPnlSnapshot>
 */
class BotPnlSnapshotFactory extends Factory
{
    public function definition(): array
    {
        $gridProfit = fake()->randomFloat(4, 0, 5);
        $trendPnl   = fake()->randomFloat(4, -3, 3);

        return [
            'bot_id'         => Bot::factory(),
            'total_pnl'      => round($gridProfit + $trendPnl, 4),
            'grid_profit'    => $gridProfit,
            'trend_pnl'      => $trendPnl,
            'unrealized_pnl' => 0.0,
            'snapshot_at'    => now(),
        ];
    }
}
