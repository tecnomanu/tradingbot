<?php

namespace Tests\Feature\Api;

use App\Models\Bot;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(User $user): array
    {
        return ['X-API-Key' => $user->api_key];
    }

    // -------------------------------------------------------------------------
    // global  GET /api/v1/orders
    // -------------------------------------------------------------------------

    public function test_global_requires_api_key(): void
    {
        $response = $this->getJson('/api/v1/orders');

        $response->assertUnauthorized();
    }

    public function test_global_returns_only_user_orders(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $myBot    = Bot::factory()->create(['user_id' => $user->id]);
        $otherBot = Bot::factory()->create(['user_id' => $other->id]);

        Order::factory()->count(3)->create(['bot_id' => $myBot->id]);
        Order::factory()->count(5)->create(['bot_id' => $otherBot->id]);

        $response = $this->getJson('/api/v1/orders', $this->apiHeaders($user));

        $response->assertOk();
        $this->assertCount(3, $response->json('data.orders'));
    }

    public function test_global_returns_aggregate_stats(): void
    {
        $user  = User::factory()->create();
        $bot   = Bot::factory()->create(['user_id' => $user->id]);

        Order::factory()->count(4)->create(['bot_id' => $bot->id]);
        Order::factory()->filled()->count(2)->create(['bot_id' => $bot->id]);

        $response = $this->getJson('/api/v1/orders', $this->apiHeaders($user));

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'aggregate' => ['total', 'open', 'filled', 'cancelled', 'total_pnl_usdt'],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // byBot  GET /api/v1/orders/bot/{bot}
    // -------------------------------------------------------------------------

    public function test_by_bot_returns_orders_for_bot(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->create(['user_id' => $user->id]);

        Order::factory()->count(5)->create(['bot_id' => $bot->id]);

        $response = $this->getJson("/api/v1/orders/bot/{$bot->id}", $this->apiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.bot_id', $bot->id)
            ->assertJsonPath('data.total', 5);
    }

    public function test_by_bot_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson("/api/v1/orders/bot/{$bot->id}", $this->apiHeaders($user));

        $response->assertForbidden();
    }

    public function test_by_bot_filters_by_status(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->create(['user_id' => $user->id]);

        Order::factory()->count(3)->create(['bot_id' => $bot->id]); // open
        Order::factory()->filled()->count(2)->create(['bot_id' => $bot->id]);

        $response = $this->getJson(
            "/api/v1/orders/bot/{$bot->id}?status=open",
            $this->apiHeaders($user)
        );

        $response->assertOk()->assertJsonPath('data.total', 3);
    }

    // -------------------------------------------------------------------------
    // stats  GET /api/v1/orders/bot/{bot}/stats
    // -------------------------------------------------------------------------

    public function test_stats_returns_expected_structure(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->create(['user_id' => $user->id]);

        Order::factory()->filled()->count(4)->create(['bot_id' => $bot->id]);

        $response = $this->getJson("/api/v1/orders/bot/{$bot->id}/stats", $this->apiHeaders($user));

        $response->assertOk()->assertJsonStructure([
            'data' => [
                'bot_id', 'open', 'filled', 'filled_24h', 'rounds_24h',
                'buys', 'sells', 'pnl' => ['total', 'best', 'worst'],
            ],
        ]);
    }

    public function test_stats_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson("/api/v1/orders/bot/{$bot->id}/stats", $this->apiHeaders($user));

        $response->assertForbidden();
    }

    public function test_stats_counts_are_accurate(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->create(['user_id' => $user->id]);

        Order::factory()->count(5)->create(['bot_id' => $bot->id]); // open
        Order::factory()->filled()->buy()->count(3)->create(['bot_id' => $bot->id]);
        Order::factory()->filled()->sell()->count(2)->create(['bot_id' => $bot->id]);

        $response = $this->getJson("/api/v1/orders/bot/{$bot->id}/stats", $this->apiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.open', 5)
            ->assertJsonPath('data.filled', 5)
            ->assertJsonPath('data.buys', 3)
            ->assertJsonPath('data.sells', 2);
    }
}
