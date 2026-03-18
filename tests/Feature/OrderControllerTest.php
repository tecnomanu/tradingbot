<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // bots (GET /orders/bots)
    // -------------------------------------------------------------------------

    public function test_bots_requires_authentication(): void
    {
        $response = $this->get(route('orders.bots'));

        $response->assertRedirect(route('login'));
    }

    public function test_bots_renders_inertia_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('orders.bots'));

        $response->assertInertia(fn ($page) => $page->component('Orders/Bots'));
    }

    public function test_bots_returns_only_user_bots(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Bot::factory()->active()->create(['user_id' => $user->id]);
        Bot::factory()->active()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('orders.bots'));

        $response->assertInertia(
            fn ($page) => $page
                ->component('Orders/Bots')
                ->has('activeBots', 1)
        );
    }

    public function test_bots_separates_active_and_stopped_bots(): void
    {
        $user = User::factory()->create();

        Bot::factory()->active()->create(['user_id' => $user->id]);
        Bot::factory()->stopped()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('orders.bots'));

        $response->assertInertia(
            fn ($page) => $page
                ->has('activeBots', 1)
                ->has('stoppedBots', 1)
        );
    }

    // -------------------------------------------------------------------------
    // orderHistory (GET /orders/history)
    // -------------------------------------------------------------------------

    public function test_order_history_requires_authentication(): void
    {
        $response = $this->get(route('orders.history'));

        $response->assertRedirect(route('login'));
    }

    public function test_order_history_renders_inertia_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('orders.history'));

        $response->assertInertia(fn ($page) => $page->component('Orders/OrderHistory'));
    }

    public function test_order_history_only_returns_user_orders(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $myBot    = Bot::factory()->create(['user_id' => $user->id]);
        $otherBot = Bot::factory()->create(['user_id' => $other->id]);

        Order::factory()->filled()->count(3)->create(['bot_id' => $myBot->id]);
        Order::factory()->filled()->count(5)->create(['bot_id' => $otherBot->id]);

        $response = $this->actingAs($user)->get(route('orders.history'));

        $response->assertInertia(
            fn ($page) => $page->where('orders.total', 3)
        );
    }

    public function test_order_history_returns_filters_in_props(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('orders.history'));

        $response->assertInertia(fn ($page) => $page->has('filters'));
    }

    // -------------------------------------------------------------------------
    // positions (GET /orders/positions)
    // -------------------------------------------------------------------------

    public function test_positions_requires_authentication(): void
    {
        $response = $this->get(route('orders.positions'));

        $response->assertRedirect(route('login'));
    }

    public function test_positions_renders_inertia_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('orders.positions'));

        $response->assertInertia(fn ($page) => $page->component('Orders/Positions'));
    }

    public function test_positions_only_returns_active_bot_positions(): void
    {
        $user = User::factory()->create();

        Bot::factory()->active()->count(2)->create(['user_id' => $user->id]);
        Bot::factory()->stopped()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('orders.positions'));

        $response->assertInertia(fn ($page) => $page->has('positions', 2));
    }
}
