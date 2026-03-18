<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BinanceAccount>
 */
class BinanceAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'label'      => fake()->words(2, true) . ' Account',
            'api_key'    => Str::random(64),
            'api_secret' => Str::random(64),
            'is_testnet' => true,
            'is_active'  => true,
        ];
    }

    public function testnet(): static
    {
        return $this->state(['is_testnet' => true]);
    }

    public function mainnet(): static
    {
        return $this->state(['is_testnet' => false]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
