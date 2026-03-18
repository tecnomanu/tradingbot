<?php

namespace Tests\Feature;

use App\Enums\BotStatus;
use App\Models\BinanceAccount;
use App\Models\Bot;
use App\Models\User;
use App\Services\BinanceApiService;
use App\Services\GridTradingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BotControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('bots.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_renders_inertia_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('bots.index'));

        $response->assertInertia(fn ($page) => $page->component('Bots/Index'));
    }

    public function test_index_returns_only_authenticated_user_bots(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Bot::factory()->count(2)->create(['user_id' => $user->id]);
        Bot::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('bots.index'));

        $response->assertInertia(
            fn ($page) => $page
                ->component('Bots/Index')
                ->has('bots', 2)
        );
    }

    public function test_index_returns_supported_pairs_and_limits(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('bots.index'));

        $response->assertInertia(
            fn ($page) => $page
                ->has('supportedPairs')
                ->has('gridLimits')
                ->has('sides')
        );
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_bot_and_redirects(): void
    {
        Queue::fake();

        $user    = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('bots.store'), [
            'binance_account_id' => $account->id,
            'name'               => 'My Bot',
            'symbol'             => 'BTCUSDT',
            'side'               => 'long',
            'price_lower'        => 80000,
            'price_upper'        => 100000,
            'grid_count'         => 10,
            'investment'         => 100,
            'leverage'           => 1,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bots', ['name' => 'My Bot', 'user_id' => $user->id]);
    }

    public function test_store_fails_validation_when_price_upper_less_than_lower(): void
    {
        $user    = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('bots.store'), [
            'binance_account_id' => $account->id,
            'name'               => 'Bad Bot',
            'symbol'             => 'BTCUSDT',
            'side'               => 'long',
            'price_lower'        => 100000,
            'price_upper'        => 80000,
            'grid_count'         => 10,
            'investment'         => 100,
            'leverage'           => 1,
        ]);

        $response->assertSessionHasErrors('price_upper');
    }

    public function test_store_fails_validation_when_grid_count_below_minimum(): void
    {
        $user    = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('bots.store'), [
            'binance_account_id' => $account->id,
            'name'               => 'Bad Bot',
            'symbol'             => 'BTCUSDT',
            'side'               => 'long',
            'price_lower'        => 80000,
            'price_upper'        => 100000,
            'grid_count'         => 1,
            'investment'         => 100,
            'leverage'           => 1,
        ]);

        $response->assertSessionHasErrors('grid_count');
    }

    public function test_store_requires_valid_binance_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('bots.store'), [
            'binance_account_id' => 9999,
            'name'               => 'Bad Bot',
            'symbol'             => 'BTCUSDT',
            'side'               => 'long',
            'price_lower'        => 80000,
            'price_upper'        => 100000,
            'grid_count'         => 10,
            'investment'         => 100,
            'leverage'           => 1,
        ]);

        $response->assertSessionHasErrors('binance_account_id');
    }

    // -------------------------------------------------------------------------
    // show
    // -------------------------------------------------------------------------

    public function test_show_renders_bot_detail_page(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('bots.show', $bot));

        $response->assertInertia(fn ($page) => $page->component('Bots/Show'));
    }

    public function test_show_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('bots.show', $bot));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // start
    // -------------------------------------------------------------------------

    public function test_start_dispatches_job_for_pending_bot(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $bot  = Bot::factory()->create([
            'user_id' => $user->id,
            'status'  => BotStatus::Pending->value,
        ]);

        $response = $this->actingAs($user)->post(route('bots.start', $bot));

        $response->assertRedirect();
        Queue::assertPushed(\App\Jobs\InitializeBotJob::class);
    }

    public function test_start_already_active_bot_shows_warning(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->active()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('bots.start', $bot));

        $response->assertSessionHas('warning');
    }

    public function test_start_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->post(route('bots.start', $bot));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // stop
    // -------------------------------------------------------------------------

    public function test_stop_active_bot_stops_and_shows_success(): void
    {
        $engineMock = $this->createMock(GridTradingEngine::class);
        $engineMock->expects($this->once())->method('stopBot');
        $this->app->instance(GridTradingEngine::class, $engineMock);

        $user = User::factory()->create();
        $bot  = Bot::factory()->active()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('bots.stop', $bot));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_stop_non_active_bot_shows_warning(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->stopped()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('bots.stop', $bot));

        $response->assertSessionHas('warning');
    }

    // -------------------------------------------------------------------------
    // calculateGrid
    // -------------------------------------------------------------------------

    public function test_calculate_grid_returns_json_config(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('bots.calculate-grid'), [
            'price_lower' => 80000,
            'price_upper' => 100000,
            'grid_count'  => 10,
            'investment'  => 100,
            'leverage'    => 1,
            'side'        => 'long',
        ]);

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => ['grid_levels', 'profit_per_grid', 'step_size'],
        ]);
    }

    public function test_calculate_grid_fails_when_upper_less_than_lower(): void
    {
        $user = User::factory()->create();

        // Web route: validation failure redirects back with session errors
        $response = $this->actingAs($user)->post(route('bots.calculate-grid'), [
            'price_lower' => 100000,
            'price_upper' => 80000,
            'grid_count'  => 10,
            'investment'  => 100,
            'leverage'    => 1,
            'side'        => 'long',
        ]);

        $response->assertSessionHasErrors('price_upper');
    }

    // -------------------------------------------------------------------------
    // currentPrice
    // -------------------------------------------------------------------------

    public function test_current_price_returns_price_when_available(): void
    {
        $user = User::factory()->create();

        $mock = $this->createMock(BinanceApiService::class);
        $mock->method('getCurrentPrice')->willReturn(90000.0);
        $this->app->instance(BinanceApiService::class, $mock);

        $response = $this->actingAs($user)->postJson(route('bots.current-price'), [
            'symbol' => 'BTCUSDT',
        ]);

        $response->assertOk();
        $this->assertEquals(90000.0, $response->json('data.price'));
    }

    public function test_current_price_returns_error_when_unavailable(): void
    {
        $user = User::factory()->create();

        $mock = $this->createMock(BinanceApiService::class);
        $mock->method('getCurrentPrice')->willReturn(null);
        $this->app->instance(BinanceApiService::class, $mock);

        $response = $this->actingAs($user)->postJson(route('bots.current-price'), [
            'symbol' => 'BTCUSDT',
        ]);

        // errorResponse() returns 400 with success: false
        $response->assertStatus(400)->assertJsonPath('success', false);
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_bot_and_redirects_to_index(): void
    {
        $engineMock = $this->createMock(GridTradingEngine::class);
        $this->app->instance(GridTradingEngine::class, $engineMock);

        $user = User::factory()->create();
        $bot  = Bot::factory()->stopped()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('bots.destroy', $bot));

        $response->assertRedirect(route('bots.index'));
        $this->assertDatabaseMissing('bots', ['id' => $bot->id]);
    }

    public function test_destroy_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->delete(route('bots.destroy', $bot));

        $response->assertForbidden();
    }
}
