<?php

namespace Tests\Feature\Api;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\User;
use App\Services\GridTradingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BotApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(User $user): array
    {
        return ['X-API-Key' => $user->api_key];
    }

    // -------------------------------------------------------------------------
    // index  GET /api/v1/bots
    // -------------------------------------------------------------------------

    public function test_index_returns_only_authenticated_user_bots(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Bot::factory()->count(3)->create(['user_id' => $user->id]);
        Bot::factory()->count(2)->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/v1/bots', $this->apiHeaders($user));

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_returns_expected_bot_fields(): void
    {
        $user = User::factory()->create();
        Bot::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/v1/bots', $this->apiHeaders($user));

        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'symbol', 'status', 'side', 'investment', 'leverage'],
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // show  GET /api/v1/bots/{bot}
    // -------------------------------------------------------------------------

    public function test_show_returns_bot_detail(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/v1/bots/{$bot->id}", $this->apiHeaders($user));

        $response->assertOk()->assertJsonStructure([
            'data' => ['bot', 'config', 'orders', 'pnl_history'],
        ]);
    }

    public function test_show_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->create(['user_id' => $other->id]);

        $response = $this->getJson("/api/v1/bots/{$bot->id}", $this->apiHeaders($user));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // start  POST /api/v1/bots/{bot}/start
    // -------------------------------------------------------------------------

    public function test_start_dispatches_job_for_pending_bot(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $bot  = Bot::factory()->create([
            'user_id' => $user->id,
            'status'  => BotStatus::Pending->value,
        ]);

        $response = $this->postJson("/api/v1/bots/{$bot->id}/start", [], $this->apiHeaders($user));

        $response->assertOk();
        Queue::assertPushed(\App\Jobs\InitializeBotJob::class);
    }

    public function test_start_already_active_bot_returns_409(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->active()->create(['user_id' => $user->id]);

        $response = $this->postJson("/api/v1/bots/{$bot->id}/start", [], $this->apiHeaders($user));

        $response->assertStatus(409);
    }

    public function test_start_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->create(['user_id' => $other->id]);

        $response = $this->postJson("/api/v1/bots/{$bot->id}/start", [], $this->apiHeaders($user));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // stop  POST /api/v1/bots/{bot}/stop
    // -------------------------------------------------------------------------

    public function test_stop_active_bot_succeeds(): void
    {
        $engineMock = $this->createMock(GridTradingEngine::class);
        $engineMock->method('stopBot');
        $this->app->instance(GridTradingEngine::class, $engineMock);

        $user = User::factory()->create();
        $bot  = Bot::factory()->active()->create(['user_id' => $user->id]);

        $response = $this->postJson("/api/v1/bots/{$bot->id}/stop", [], $this->apiHeaders($user));

        $response->assertOk();
    }

    public function test_stop_non_active_bot_returns_409(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->stopped()->create(['user_id' => $user->id]);

        $response = $this->postJson("/api/v1/bots/{$bot->id}/stop", [], $this->apiHeaders($user));

        $response->assertStatus(409);
    }

    public function test_stop_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->active()->create(['user_id' => $other->id]);

        $response = $this->postJson("/api/v1/bots/{$bot->id}/stop", [], $this->apiHeaders($user));

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // update  PATCH /api/v1/bots/{bot}
    // -------------------------------------------------------------------------

    public function test_update_active_bot_returns_409(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->active()->create(['user_id' => $user->id]);

        $response = $this->patchJson("/api/v1/bots/{$bot->id}", [
            'name' => 'New Name',
        ], $this->apiHeaders($user));

        $response->assertStatus(409);
    }

    public function test_update_stopped_bot_with_valid_fields(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->stopped()->create(['user_id' => $user->id]);

        $response = $this->patchJson("/api/v1/bots/{$bot->id}", [
            'name' => 'Renamed Bot',
        ], $this->apiHeaders($user));

        $response->assertOk()->assertJsonPath('data.name', 'Renamed Bot');
        $this->assertDatabaseHas('bots', ['id' => $bot->id, 'name' => 'Renamed Bot']);
    }

    public function test_update_without_valid_fields_returns_422(): void
    {
        $user = User::factory()->create();
        $bot  = Bot::factory()->stopped()->create(['user_id' => $user->id]);

        $response = $this->patchJson("/api/v1/bots/{$bot->id}", [], $this->apiHeaders($user));

        $response->assertStatus(422);
    }

    public function test_update_returns_403_for_other_user_bot(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $bot   = Bot::factory()->stopped()->create(['user_id' => $other->id]);

        $response = $this->patchJson("/api/v1/bots/{$bot->id}", [
            'name' => 'Hijack',
        ], $this->apiHeaders($user));

        $response->assertForbidden();
    }
}
