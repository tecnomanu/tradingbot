<?php

namespace Database\Factories;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use App\Models\Bot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bot_id'     => Bot::factory(),
            'side'       => OrderSide::Buy->value,
            'status'     => OrderStatus::Open->value,
            'price'      => fake()->randomFloat(2, 80000, 100000),
            'quantity'   => fake()->randomFloat(8, 0.0001, 0.001),
            'grid_level' => fake()->numberBetween(0, 10),
            'pnl'        => 0.0,
        ];
    }

    public function buy(): static
    {
        return $this->state(['side' => OrderSide::Buy->value]);
    }

    public function sell(): static
    {
        return $this->state(['side' => OrderSide::Sell->value]);
    }

    public function filled(): static
    {
        return $this->state([
            'status'    => OrderStatus::Filled->value,
            'filled_at' => now(),
            'pnl'       => fake()->randomFloat(4, 0.01, 2.0),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => OrderStatus::Cancelled->value]);
    }
}
