<?php

namespace Tests\Feature\Api;

use App\Enums\BotStatus;
use App\Models\Bot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusApiControllerTest extends TestCase
{
    use RefreshDatabase;

    private function apiHeaders(User $user): array
    {
        return ['X-API-Key' => $user->api_key];
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_overview_returns_401_without_api_key(): void
    {
        $response = $this->getJson('/api/v1/status');

        $response->assertUnauthorized();
    }

    public function test_overview_returns_401_with_invalid_api_key(): void
    {
        $response = $this->getJson('/api/v1/status', ['X-API-Key' => 'invalid-key']);

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Successful responses
    // -------------------------------------------------------------------------

    public function test_overview_returns_200_with_valid_api_key(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/status', $this->apiHeaders($user));

        $response->assertOk();
    }

    public function test_overview_returns_expected_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/status', $this->apiHeaders($user));

        $response->assertJsonStructure([
            'success',
            'data' => [
                'timestamp',
                'bots' => ['total', 'active', 'stopped', 'error', 'active_list'],
                'orders' => ['open', 'filled', 'filled_24h', 'total_pnl'],
                'horizon',
            ],
        ]);
    }

    public function test_overview_counts_reflect_user_bots(): void
    {
        $user = User::factory()->create();

        Bot::factory()->active()->count(2)->create(['user_id' => $user->id]);
        Bot::factory()->stopped()->count(1)->create(['user_id' => $user->id]);

        $response = $this->getJson('/api/v1/status', $this->apiHeaders($user));

        $response->assertOk()
            ->assertJsonPath('data.bots.total', 3)
            ->assertJsonPath('data.bots.active', 2)
            ->assertJsonPath('data.bots.stopped', 1);
    }

    public function test_overview_does_not_return_other_user_bots(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Bot::factory()->active()->count(5)->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/v1/status', $this->apiHeaders($user));

        $response->assertJsonPath('data.bots.total', 0);
    }

    public function test_overview_accepts_bearer_token_auth(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/status', [
            'Authorization' => 'Bearer ' . $user->api_key,
        ]);

        $response->assertOk();
    }
}
