<?php

namespace Tests\Unit;

use App\Models\Bot;
use App\Models\BotPnlSnapshot;
use App\Services\PnlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PnlServiceTest extends TestCase
{
    use RefreshDatabase;

    private PnlService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PnlService::class);
    }

    // -------------------------------------------------------------------------
    // takeSnapshot
    // -------------------------------------------------------------------------

    public function test_take_snapshot_creates_record_in_db(): void
    {
        $bot = Bot::factory()->create([
            'total_pnl'   => 5.1234,
            'grid_profit' => 6.0,
            'trend_pnl'   => -0.8766,
        ]);

        $snapshot = $this->service->takeSnapshot($bot);

        $this->assertInstanceOf(BotPnlSnapshot::class, $snapshot);
        $this->assertDatabaseHas('bot_pnl_snapshots', [
            'bot_id'      => $bot->id,
            'total_pnl'   => 5.1234,
            'grid_profit' => 6.0,
            'trend_pnl'   => -0.8766,
        ]);
    }

    public function test_take_snapshot_stores_bot_pnl_values(): void
    {
        $bot = Bot::factory()->create([
            'total_pnl'   => 12.5,
            'grid_profit' => 15.0,
            'trend_pnl'   => -2.5,
        ]);

        $snapshot = $this->service->takeSnapshot($bot);

        $this->assertEquals(12.5, (float) $snapshot->total_pnl);
        $this->assertEquals(15.0, (float) $snapshot->grid_profit);
        $this->assertEquals(-2.5, (float) $snapshot->trend_pnl);
        $this->assertNotNull($snapshot->snapshot_at);
    }

    // -------------------------------------------------------------------------
    // getHistoricalPnl
    // -------------------------------------------------------------------------

    public function test_get_historical_pnl_returns_snapshots_within_range(): void
    {
        $bot = Bot::factory()->create();

        BotPnlSnapshot::factory()->create([
            'bot_id'      => $bot->id,
            'total_pnl'   => 1.0,
            'snapshot_at' => now()->subHours(10),
        ]);
        BotPnlSnapshot::factory()->create([
            'bot_id'      => $bot->id,
            'total_pnl'   => 2.0,
            'snapshot_at' => now()->subHours(30),
        ]);

        $result = $this->service->getHistoricalPnl($bot->id, 24);

        $this->assertCount(1, $result);
        $this->assertEquals(1.0, $result[0]['total_pnl']);
    }

    public function test_get_historical_pnl_excludes_snapshots_outside_range(): void
    {
        $bot = Bot::factory()->create();

        BotPnlSnapshot::factory()->create([
            'bot_id'      => $bot->id,
            'snapshot_at' => now()->subHours(49),
        ]);

        $result = $this->service->getHistoricalPnl($bot->id, 48);

        $this->assertCount(0, $result);
    }

    public function test_get_historical_pnl_returns_expected_keys(): void
    {
        $bot = Bot::factory()->create();

        BotPnlSnapshot::factory()->create([
            'bot_id'      => $bot->id,
            'snapshot_at' => now()->subHours(1),
        ]);

        $result = $this->service->getHistoricalPnl($bot->id);

        $this->assertArrayHasKey('time', $result[0]);
        $this->assertArrayHasKey('total_pnl', $result[0]);
        $this->assertArrayHasKey('grid_profit', $result[0]);
        $this->assertArrayHasKey('trend_pnl', $result[0]);
    }

    public function test_get_historical_pnl_returns_empty_when_no_snapshots(): void
    {
        $bot = Bot::factory()->create();

        $result = $this->service->getHistoricalPnl($bot->id);

        $this->assertEmpty($result);
    }

    // -------------------------------------------------------------------------
    // getDashboardSummary
    // -------------------------------------------------------------------------

    public function test_get_dashboard_summary_returns_expected_keys(): void
    {
        $user = \App\Models\User::factory()->create();

        $result = $this->service->getDashboardSummary($user->id);

        $this->assertArrayHasKey('stats', $result);
        $this->assertArrayHasKey('active_bots', $result);
        $this->assertArrayHasKey('pnl_chart', $result);
    }

    public function test_get_dashboard_summary_pnl_chart_aggregates_active_bots(): void
    {
        $user = \App\Models\User::factory()->create();
        $bot  = Bot::factory()->active()->create(['user_id' => $user->id]);

        BotPnlSnapshot::factory()->create([
            'bot_id'      => $bot->id,
            'total_pnl'   => 3.0,
            'grid_profit' => 3.0,
            'snapshot_at' => now()->subHour(),
        ]);

        $result = $this->service->getDashboardSummary($user->id);

        $this->assertNotEmpty($result['pnl_chart']);
    }
}
