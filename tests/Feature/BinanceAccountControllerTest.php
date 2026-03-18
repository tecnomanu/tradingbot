<?php

namespace Tests\Feature;

use App\Models\BinanceAccount;
use App\Models\Bot;
use App\Models\User;
use App\Services\BinanceApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BinanceAccountControllerTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('binance-accounts.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_index_renders_inertia_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('binance-accounts.index'));

        $response->assertInertia(fn ($page) => $page->component('BinanceAccounts/Index'));
    }

    public function test_index_returns_only_current_user_accounts(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        BinanceAccount::factory()->count(2)->create(['user_id' => $user->id]);
        BinanceAccount::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('binance-accounts.index'));

        $response->assertInertia(fn ($page) => $page->has('accounts', 2));
    }

    // -------------------------------------------------------------------------
    // store
    // -------------------------------------------------------------------------

    public function test_store_creates_account(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('binance-accounts.store'), [
            'label'      => 'My Testnet Account',
            'api_key'    => str_repeat('a', 64),
            'api_secret' => str_repeat('b', 64),
            'is_testnet' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('binance_accounts', [
            'label'   => 'My Testnet Account',
            'user_id' => $user->id,
        ]);
    }

    public function test_store_requires_label(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('binance-accounts.store'), [
            'api_key'    => str_repeat('a', 64),
            'api_secret' => str_repeat('b', 64),
        ]);

        $response->assertSessionHasErrors('label');
    }

    public function test_store_requires_api_key_minimum_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('binance-accounts.store'), [
            'label'      => 'My Account',
            'api_key'    => 'short',
            'api_secret' => str_repeat('b', 64),
        ]);

        $response->assertSessionHasErrors('api_key');
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function test_update_modifies_account(): void
    {
        $user    = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->patch(route('binance-accounts.update', $account), [
            'label' => 'Updated Label',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('binance_accounts', [
            'id'    => $account->id,
            'label' => 'Updated Label',
        ]);
    }

    public function test_update_returns_403_for_other_user_account(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->patch(route('binance-accounts.update', $account), [
            'label' => 'Hijacked',
        ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // destroy
    // -------------------------------------------------------------------------

    public function test_destroy_deletes_account(): void
    {
        $user    = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('binance-accounts.destroy', $account));

        $response->assertRedirect();
        $this->assertDatabaseMissing('binance_accounts', ['id' => $account->id]);
    }

    public function test_destroy_returns_403_for_other_user_account(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->delete(route('binance-accounts.destroy', $account));

        $response->assertForbidden();
    }

    public function test_destroy_blocked_when_active_bot_exists(): void
    {
        $user    = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $user->id]);

        Bot::factory()->active()->create([
            'user_id'            => $user->id,
            'binance_account_id' => $account->id,
        ]);

        $response = $this->actingAs($user)->delete(route('binance-accounts.destroy', $account));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('binance_accounts', ['id' => $account->id]);
    }

    // -------------------------------------------------------------------------
    // testConnection
    // -------------------------------------------------------------------------

    public function test_test_connection_returns_json_success(): void
    {
        $user    = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $user->id]);

        $mock = $this->createMock(BinanceApiService::class);
        $mock->method('testConnection')->willReturn([
            'success' => true,
            'message' => 'Connection OK',
            'data'    => ['balance' => 100],
        ]);
        $this->app->instance(BinanceApiService::class, $mock);

        $response = $this->actingAs($user)->postJson(
            route('binance-accounts.test', $account)
        );

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_test_connection_returns_403_for_other_user_account(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $account = BinanceAccount::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->postJson(
            route('binance-accounts.test', $account)
        );

        $response->assertForbidden();
    }
}
