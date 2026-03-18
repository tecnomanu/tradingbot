<?php

namespace App\Http\Controllers\Api;

use App\Enums\BotStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateBotRequest;
use App\Http\Resources\BotSummaryResource;
use App\Http\Resources\OrderResource;
use App\Models\Bot;
use App\Services\BotActivityLogger;
use App\Services\BotService;
use App\Services\GridCalculatorService;
use App\Services\PnlService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotApiController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BotService $botService,
        private GridCalculatorService $gridCalculator,
        private PnlService $pnlService,
    ) {}

    /**
     * List all bots for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $bots = Bot::where('user_id', $request->user()->id)
            ->with('binanceAccount:id,label,is_testnet')
            ->withCount([
                'orders as open_orders_count'   => fn ($q) => $q->where('status', 'open'),
                'orders as filled_orders_count' => fn ($q) => $q->where('status', 'filled'),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Bot $b) => $this->formatBotSummary($b));

        return $this->successResponse($bots, 'Bots retrieved');
    }

    /**
     * Get full details of a single bot: config, stats, orders, PNL history.
     */
    public function show(Request $request, Bot $bot): JsonResponse
    {
        abort_if($bot->user_id !== $request->user()->id, 403, 'Forbidden');
        $bot->load([
            'binanceAccount:id,label,is_testnet',
            'orders' => fn ($q) => $q->orderBy('price'),
        ]);

        $pnlHistory  = $this->pnlService->getHistoricalPnl($bot->id);
        $gridConfig  = $this->gridCalculator->calculateFullGridConfig([
            'price_lower' => $bot->price_lower,
            'price_upper' => $bot->price_upper,
            'grid_count'  => $bot->grid_count,
            'investment'  => $bot->investment,
            'leverage'    => $bot->leverage,
            'side'        => $bot->side->value,
        ]);

        $ordersByStatus = $bot->orders->groupBy(fn ($o) => $o->status->value);

        return $this->successResponse([
            'bot'         => $this->formatBotSummary($bot),
            'config'      => $this->formatGridConfig($gridConfig),
            'orders'      => [
                'open'      => OrderResource::collection($ordersByStatus->get('open', collect())),
                'filled'    => OrderResource::collection($ordersByStatus->get('filled', collect())),
                'cancelled' => OrderResource::collection($ordersByStatus->get('cancelled', collect())),
            ],
            'pnl_history' => $pnlHistory,
        ]);
    }

    /**
     * Start a stopped/pending/error bot.
     */
    public function start(Request $request, Bot $bot): JsonResponse
    {
        abort_if($bot->user_id !== $request->user()->id, 403, 'Forbidden');

        if ($bot->status === BotStatus::Active) {
            return $this->errorResponse('Bot is already active', 409);
        }

        $this->botService->startBot($bot);

        BotActivityLogger::logApiAction($bot, 'bot_started', $request->user());

        return $this->successResponse(
            ['bot_id' => $bot->id, 'status' => 'pending'],
            'Bot start dispatched. Orders will be placed on Binance shortly.'
        );
    }

    /**
     * Stop an active bot, cancelling all open orders on Binance.
     */
    public function stop(Request $request, Bot $bot): JsonResponse
    {
        abort_if($bot->user_id !== $request->user()->id, 403, 'Forbidden');

        if ($bot->status !== BotStatus::Active) {
            return $this->errorResponse('Bot is not active', 409);
        }

        $this->botService->stopBot($bot);
        $bot->refresh();

        BotActivityLogger::logApiAction($bot, 'bot_stopped', $request->user());

        return $this->successResponse(
            ['bot_id' => $bot->id, 'status' => $bot->status->value],
            'Bot stopped. All open orders cancelled.'
        );
    }

    /**
     * Update bot config (only when stopped).
     */
    public function update(UpdateBotRequest $request, Bot $bot): JsonResponse
    {
        abort_if($bot->user_id !== $request->user()->id, 403, 'Forbidden');

        if ($bot->status === BotStatus::Active) {
            return $this->errorResponse('Cannot update an active bot. Stop it first.', 409);
        }

        $validated = $request->validated();

        if (count($validated) === 0) {
            return $this->errorResponse('No valid fields provided', 422);
        }

        $beforeState = BotActivityLogger::captureState($bot);

        // Recalculate grid if price/grid params changed
        if (array_intersect_key($validated, array_flip(['price_lower', 'price_upper', 'grid_count', 'investment', 'leverage']))) {
            $gridConfig = $this->gridCalculator->calculateFullGridConfig([
                'price_lower' => $validated['price_lower'] ?? $bot->price_lower,
                'price_upper' => $validated['price_upper'] ?? $bot->price_upper,
                'grid_count'  => $validated['grid_count']  ?? $bot->grid_count,
                'investment'  => $validated['investment']  ?? $bot->investment,
                'leverage'    => $validated['leverage']    ?? $bot->leverage,
                'side'        => $bot->side->value,
            ]);
            $validated = array_merge($validated, [
                'real_investment'        => $gridConfig['real_investment'],
                'additional_margin'      => $gridConfig['additional_margin'],
                'est_liquidation_price'  => $gridConfig['est_liquidation_price'],
                'profit_per_grid'        => $gridConfig['profit_per_grid'],
                'commission_per_grid'    => $gridConfig['commission_per_grid'],
            ]);
        }

        $bot->update($validated);

        BotActivityLogger::logApiAction($bot, 'bot_updated', $request->user(), [
            'fields_changed' => array_keys($validated),
        ], $beforeState, BotActivityLogger::captureState($bot->fresh()));

        return $this->successResponse($this->formatBotSummary($bot->fresh()), 'Bot updated');
    }

    // -------------------------------------------------------------------------
    // Internal formatters
    // -------------------------------------------------------------------------

    private function formatBotSummary(Bot $bot): array
    {
        return [
            'id'                    => $bot->id,
            'name'                  => $bot->name,
            'symbol'                => $bot->symbol,
            'side'                  => $bot->side->value,
            'status'                => $bot->status->value,
            'account'               => $bot->binanceAccount?->label,
            'is_testnet'            => (bool) $bot->binanceAccount?->is_testnet,
            'price_lower'           => (float) $bot->price_lower,
            'price_upper'           => (float) $bot->price_upper,
            'grid_count'            => $bot->grid_count,
            'investment'            => (float) $bot->investment,
            'real_investment'       => (float) $bot->real_investment,
            'leverage'              => $bot->leverage,
            'stop_loss_price'       => $bot->stop_loss_price  ? (float) $bot->stop_loss_price  : null,
            'take_profit_price'     => $bot->take_profit_price ? (float) $bot->take_profit_price : null,
            'total_pnl'             => (float) $bot->total_pnl,
            'grid_profit'           => (float) $bot->grid_profit,
            'trend_pnl'             => (float) $bot->trend_pnl,
            'pnl_pct'               => $bot->pnl_percentage,
            'total_rounds'          => (int) $bot->total_rounds,
            'rounds_24h'            => (int) $bot->rounds_24h,
            'profit_per_grid'       => (float) $bot->profit_per_grid,
            'est_liquidation_price' => $bot->est_liquidation_price ? (float) $bot->est_liquidation_price : null,
            'open_orders_count'     => (int) ($bot->open_orders_count   ?? $bot->orders->where('status', 'open')->count()),
            'filled_orders_count'   => (int) ($bot->filled_orders_count ?? $bot->orders->where('status', 'filled')->count()),
            'started_at'            => $bot->started_at?->toIso8601String(),
            'stopped_at'            => $bot->stopped_at?->toIso8601String(),
            'created_at'            => $bot->created_at?->toIso8601String(),
            'updated_at'            => $bot->updated_at?->toIso8601String(),
        ];
    }

    private function formatGridConfig(array $config): array
    {
        return [
            'grid_levels'       => $config['grid_levels'] ?? [],
            'quantity_per_grid' => $config['quantity_per_grid'] ?? null,
            'real_investment'   => $config['real_investment'] ?? null,
            'additional_margin' => $config['additional_margin'] ?? null,
            'profit_per_grid'   => $config['profit_per_grid'] ?? null,
        ];
    }
}
