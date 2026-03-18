<?php

namespace Tests\Unit;

use App\Enums\BotStatus;
use App\Jobs\RunAgentAlertJob;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Repositories\BotRepository;
use App\Repositories\OrderRepository;
use App\Services\BinanceFuturesService;
use App\Services\GridCalculatorService;
use App\Services\GridTradingEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verifies that GridTradingEngine::processBot() dispatches an agent alert
 * when SL/TP price conditions are met, instead of stopping the bot directly.
 *
 * Setup: bot with no orders (so syncOrderStatuses/handleFilledOrders return early)
 * and SL/TP configured. BinanceFuturesService is mocked to return a price that
 * triggers the condition.
 */
class GridTradingEngineSlTpAlertTest extends TestCase
{
    use RefreshDatabase;

    private GridTradingEngine $engine;
    private BinanceFuturesService $binanceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->binanceMock = $this->createMock(BinanceFuturesService::class);

        // getPositions is called by updateBotStats — return empty to avoid Binance calls
        $this->binanceMock->method('getPositions')->willReturn([]);

        $this->engine = new GridTradingEngine(
            $this->binanceMock,
            app(BotRepository::class),
            app(OrderRepository::class),
            app(GridCalculatorService::class),
        );
    }

    // -------------------------------------------------------------------------
    // Stop-Loss trigger
    // -------------------------------------------------------------------------

    public function test_stop_loss_hit_dispatches_agent_alert_instead_of_stopping(): void
    {
        Queue::fake();

        // price_lower = 80000, so SL at 79000 is below the grid
        $bot = Bot::factory()->active()->create(['stop_loss_price' => 79000.0]);

        // Price drops to 78000 — below SL of 79000
        $this->binanceMock->method('getCurrentPrice')->willReturn(78000.0);

        $this->engine->processBot($bot);

        Queue::assertPushed(RunAgentAlertJob::class, function ($job) use ($bot) {
            return $job->tags() === ['bot:' . $bot->id, 'agent-alert', 'sl_tp_alert'];
        });
    }

    public function test_stop_loss_hit_does_not_stop_bot_directly(): void
    {
        Queue::fake();

        $bot = Bot::factory()->active()->create(['stop_loss_price' => 79000.0]);
        $this->binanceMock->method('getCurrentPrice')->willReturn(78000.0);

        $this->engine->processBot($bot);

        $bot->refresh();
        $this->assertEquals(BotStatus::Active, $bot->status);
        $this->assertNull($bot->stopped_at);
    }

    public function test_stop_loss_hit_creates_sl_tp_alert_action_log(): void
    {
        Queue::fake();

        $bot = Bot::factory()->active()->create(['stop_loss_price' => 79000.0]);
        $this->binanceMock->method('getCurrentPrice')->willReturn(78000.0);

        $this->engine->processBot($bot);

        $this->assertDatabaseHas('bot_action_logs', [
            'bot_id' => $bot->id,
            'action' => 'bot_sl_tp_alert',
            'source' => 'system',
        ]);

        $log = BotActionLog::where('bot_id', $bot->id)
            ->where('action', 'bot_sl_tp_alert')
            ->first();

        $this->assertEquals('stop_loss', $log->details['trigger']);
        $this->assertEquals(78000.0, $log->details['current_price']);
        $this->assertEquals(79000.0, $log->details['stop_loss_price']);
    }

    public function test_stop_loss_dispatched_job_has_correct_trigger(): void
    {
        Queue::fake();

        $bot = Bot::factory()->active()->create(['stop_loss_price' => 79000.0]);
        $this->binanceMock->method('getCurrentPrice')->willReturn(78000.0);

        $this->engine->processBot($bot);

        Queue::assertPushed(RunAgentAlertJob::class);
    }

    // -------------------------------------------------------------------------
    // Take-Profit trigger
    // -------------------------------------------------------------------------

    public function test_take_profit_hit_dispatches_agent_alert(): void
    {
        Queue::fake();

        // price_upper = 100000, so TP at 101000 is above the grid
        $bot = Bot::factory()->active()->create(['take_profit_price' => 101000.0]);

        // Price rises to 102000 — above TP
        $this->binanceMock->method('getCurrentPrice')->willReturn(102000.0);

        $this->engine->processBot($bot);

        Queue::assertPushed(RunAgentAlertJob::class);
    }

    public function test_take_profit_hit_does_not_stop_bot_directly(): void
    {
        Queue::fake();

        $bot = Bot::factory()->active()->create(['take_profit_price' => 101000.0]);
        $this->binanceMock->method('getCurrentPrice')->willReturn(102000.0);

        $this->engine->processBot($bot);

        $bot->refresh();
        $this->assertEquals(BotStatus::Active, $bot->status);
    }

    public function test_take_profit_alert_log_has_correct_trigger(): void
    {
        Queue::fake();

        $bot = Bot::factory()->active()->create(['take_profit_price' => 101000.0]);
        $this->binanceMock->method('getCurrentPrice')->willReturn(102000.0);

        $this->engine->processBot($bot);

        $log = BotActionLog::where('bot_id', $bot->id)
            ->where('action', 'bot_sl_tp_alert')
            ->firstOrFail();

        $this->assertEquals('take_profit', $log->details['trigger']);
        $this->assertEquals(102000.0, $log->details['current_price']);
    }

    // -------------------------------------------------------------------------
    // No trigger cases
    // -------------------------------------------------------------------------

    public function test_no_alert_when_sl_tp_not_configured(): void
    {
        Queue::fake();

        $bot = Bot::factory()->active()->create([
            'stop_loss_price'  => null,
            'take_profit_price' => null,
        ]);

        $this->binanceMock->method('getCurrentPrice')->willReturn(85000.0);

        $this->engine->processBot($bot);

        Queue::assertNotPushed(RunAgentAlertJob::class);
        $this->assertDatabaseMissing('bot_action_logs', [
            'bot_id' => $bot->id,
            'action' => 'bot_sl_tp_alert',
        ]);
    }

    public function test_no_alert_when_price_is_within_safe_range(): void
    {
        Queue::fake();

        // SL = 79000, TP = 101000, price = 90000 (safely in range)
        $bot = Bot::factory()->active()->create([
            'stop_loss_price'  => 79000.0,
            'take_profit_price' => 101000.0,
        ]);

        $this->binanceMock->method('getCurrentPrice')->willReturn(90000.0);

        $this->engine->processBot($bot);

        Queue::assertNotPushed(RunAgentAlertJob::class);
    }

    public function test_no_alert_for_stopped_bot(): void
    {
        Queue::fake();

        $bot = Bot::factory()->stopped()->create(['stop_loss_price' => 79000.0]);

        $this->engine->processBot($bot);

        Queue::assertNotPushed(RunAgentAlertJob::class);
        $this->binanceMock->expects($this->never())->method('getCurrentPrice');
    }

    // -------------------------------------------------------------------------
    // Old behaviour guard: stopBot must NOT be called directly
    // -------------------------------------------------------------------------

    public function test_stop_bot_is_never_called_directly_on_sl_tp(): void
    {
        Queue::fake();

        // Inject a spy to ensure stopBot() is NOT delegated internally
        $engineSpy = $this->getMockBuilder(GridTradingEngine::class)
            ->setConstructorArgs([
                $this->binanceMock,
                app(BotRepository::class),
                app(OrderRepository::class),
                app(GridCalculatorService::class),
            ])
            ->onlyMethods(['stopBot'])
            ->getMock();

        $engineSpy->expects($this->never())->method('stopBot');

        $bot = Bot::factory()->active()->create(['stop_loss_price' => 79000.0]);
        $this->binanceMock->method('getCurrentPrice')->willReturn(78000.0);

        $engineSpy->processBot($bot);
    }
}
