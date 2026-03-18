<?php

namespace Tests\Unit;

use App\Enums\BotStatus;
use App\Jobs\InitializeBotJob;
use App\Models\Bot;
use App\Models\Order;
use App\Repositories\BotRepository;
use App\Repositories\OrderRepository;
use App\Services\BotService;
use App\Services\GridCalculatorService;
use App\Services\GridTradingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BotServiceTest extends TestCase
{
    use RefreshDatabase;

    private BotService $service;
    private GridTradingEngine $engineMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engineMock = $this->createMock(GridTradingEngine::class);

        $this->service = new BotService(
            app(BotRepository::class),
            app(OrderRepository::class),
            app(GridCalculatorService::class),
            $this->engineMock,
        );
    }

    // -------------------------------------------------------------------------
    // createBot
    // -------------------------------------------------------------------------

    public function test_create_bot_persists_bot_to_database(): void
    {
        $account = \App\Models\BinanceAccount::factory()->create();
        $user    = \App\Models\User::factory()->create();

        $data = [
            'user_id'            => $user->id,
            'binance_account_id' => $account->id,
            'name'               => 'Test Bot',
            'symbol'             => 'BTCUSDT',
            'side'               => 'long',
            'price_lower'        => 80000,
            'price_upper'        => 100000,
            'grid_count'         => 10,
            'investment'         => 100,
            'leverage'           => 1,
            'grid_mode'          => 'arithmetic',
        ];

        $bot = $this->service->createBot($data);

        $this->assertInstanceOf(Bot::class, $bot);
        $this->assertDatabaseHas('bots', [
            'name'   => 'Test Bot',
            'symbol' => 'BTCUSDT',
            'status' => BotStatus::Pending->value,
        ]);
    }

    public function test_create_bot_generates_grid_orders(): void
    {
        $account = \App\Models\BinanceAccount::factory()->create();
        $user    = \App\Models\User::factory()->create();

        $gridCount = 10;
        $data = [
            'user_id'            => $user->id,
            'binance_account_id' => $account->id,
            'name'               => 'Grid Bot',
            'symbol'             => 'BTCUSDT',
            'side'               => 'long',
            'price_lower'        => 80000,
            'price_upper'        => 100000,
            'grid_count'         => $gridCount,
            'investment'         => 100,
            'leverage'           => 1,
            'grid_mode'          => 'arithmetic',
        ];

        $bot = $this->service->createBot($data);

        // Grid levels = gridCount + 1
        $this->assertEquals($gridCount + 1, Order::where('bot_id', $bot->id)->count());
    }

    public function test_create_bot_sets_status_to_pending(): void
    {
        $account = \App\Models\BinanceAccount::factory()->create();
        $user    = \App\Models\User::factory()->create();

        $bot = $this->service->createBot([
            'user_id'            => $user->id,
            'binance_account_id' => $account->id,
            'name'               => 'Test Bot',
            'symbol'             => 'BTCUSDT',
            'side'               => 'long',
            'price_lower'        => 80000,
            'price_upper'        => 100000,
            'grid_count'         => 5,
            'investment'         => 100,
            'leverage'           => 1,
        ]);

        $this->assertEquals(BotStatus::Pending, $bot->status);
    }

    // -------------------------------------------------------------------------
    // startBot
    // -------------------------------------------------------------------------

    public function test_start_bot_dispatches_initialization_job(): void
    {
        Queue::fake();

        $bot = Bot::factory()->create(['status' => BotStatus::Pending->value]);

        $this->service->startBot($bot);

        Queue::assertPushed(InitializeBotJob::class, fn ($job) => true);
    }

    public function test_start_bot_already_active_does_not_dispatch(): void
    {
        Queue::fake();

        $bot = Bot::factory()->active()->create();

        $this->service->startBot($bot);

        Queue::assertNotPushed(InitializeBotJob::class);
    }

    public function test_start_bot_returns_bot_instance(): void
    {
        Queue::fake();

        $bot = Bot::factory()->create(['status' => BotStatus::Pending->value]);

        $result = $this->service->startBot($bot);

        $this->assertInstanceOf(Bot::class, $result);
    }

    // -------------------------------------------------------------------------
    // stopBot
    // -------------------------------------------------------------------------

    public function test_stop_bot_delegates_to_grid_engine(): void
    {
        $bot = Bot::factory()->active()->create();

        $this->engineMock
            ->expects($this->once())
            ->method('stopBot')
            ->with($this->equalTo($bot));

        $this->service->stopBot($bot);
    }

    // -------------------------------------------------------------------------
    // deleteBot
    // -------------------------------------------------------------------------

    public function test_delete_bot_removes_from_database(): void
    {
        $bot = Bot::factory()->stopped()->create();

        $this->service->deleteBot($bot);

        $this->assertDatabaseMissing('bots', ['id' => $bot->id]);
    }

    public function test_delete_bot_active_stops_before_deleting(): void
    {
        $bot = Bot::factory()->active()->create();

        $this->engineMock
            ->expects($this->once())
            ->method('stopBot');

        $this->service->deleteBot($bot);
    }

    public function test_delete_bot_stopped_does_not_call_stop(): void
    {
        $bot = Bot::factory()->stopped()->create();

        $this->engineMock
            ->expects($this->never())
            ->method('stopBot');

        $this->service->deleteBot($bot);
    }

    public function test_delete_bot_removes_associated_orders(): void
    {
        $bot = Bot::factory()->stopped()->create();
        Order::factory()->count(5)->create(['bot_id' => $bot->id]);

        $this->service->deleteBot($bot);

        $this->assertEquals(0, Order::where('bot_id', $bot->id)->count());
    }

    // -------------------------------------------------------------------------
    // getBotSummary
    // -------------------------------------------------------------------------

    public function test_get_bot_summary_returns_expected_keys(): void
    {
        $bot = Bot::factory()->create();

        $summary = $this->service->getBotSummary($bot);

        $this->assertArrayHasKey('bot', $summary);
        $this->assertArrayHasKey('order_stats', $summary);
        $this->assertArrayHasKey('grid_config', $summary);
    }
}
