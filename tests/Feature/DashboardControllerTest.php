<?php

namespace Tests\Feature;

use App\Models\Bot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_inertia_component(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page->component('Dashboard/Index'));
    }

    public function test_dashboard_returns_expected_prop_keys(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(
            fn ($page) => $page
                ->has('stats')
                ->has('activeBots')
                ->has('pnlChart')
                ->has('extended')
                ->has('recentOrders')
                ->has('recentActions')
        );
    }

    public function test_dashboard_stats_reflect_user_data(): void
    {
        $user = User::factory()->create();

        Bot::factory()->active()->count(2)->create(['user_id' => $user->id]);
        Bot::factory()->stopped()->count(1)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(
            fn ($page) => $page
                ->where('stats.total_bots', 3)
                ->where('stats.active_bots', 2)
        );
    }

    public function test_dashboard_does_not_return_other_user_data(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        Bot::factory()->active()->count(5)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(
            fn ($page) => $page->where('stats.total_bots', 0)
        );
    }
}
