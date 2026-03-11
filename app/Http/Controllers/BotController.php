<?php

namespace App\Http\Controllers;

use App\Constants\BinanceConstants;
use App\Constants\GridConstants;
use App\Enums\BotSide;
use App\Enums\BotStatus;
use App\Models\Bot;
use App\Repositories\BinanceAccountRepository;
use App\Repositories\BotRepository;
use App\Repositories\OrderRepository;
use App\Services\BinanceApiService;
use App\Services\BotService;
use App\Services\GridCalculatorService;
use App\Services\PnlService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BotController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BotService $botService,
        private BotRepository $botRepository,
        private OrderRepository $orderRepository,
        private BinanceAccountRepository $binanceAccountRepository,
        private GridCalculatorService $gridCalculator,
        private PnlService $pnlService,
        private BinanceApiService $binanceApiService,
    ) {}

    /**
     * List all bots for current user.
     */
    public function index(Request $request): Response
    {
        $bots = Bot::where('user_id', $request->user()->id)
            ->withCount([
                'orders as open_orders_count' => fn ($q) => $q->where('status', 'open'),
                'orders as filled_orders_count' => fn ($q) => $q->where('status', 'filled'),
            ])
            ->orderByDesc('created_at')
            ->get();
        $accounts = $this->binanceAccountRepository->getByUser($request->user()->id);

        $activeBot = $bots->firstWhere('status', 'active');
        $botOrders = [];
        if ($activeBot) {
            $botOrders = $activeBot->orders()
                ->whereIn('status', ['open', 'filled'])
                ->orderBy('price')
                ->get(['id', 'side', 'status', 'price', 'quantity', 'filled_at', 'created_at'])
                ->map(fn ($o) => [
                    'id' => $o->id,
                    'side' => $o->side->value,
                    'status' => $o->status->value,
                    'price' => (float) $o->price,
                    'quantity' => (float) $o->quantity,
                    'time' => ($o->filled_at ?? $o->created_at)?->timestamp,
                    'created_at_fmt' => $o->created_at?->format('d/m H:i'),
                    'filled_at_fmt' => $o->filled_at?->format('d/m H:i'),
                ])
                ->values();
        }

        return Inertia::render('Bots/Index', [
            'bots' => $bots,
            'binanceAccounts' => $accounts->filter(fn($a) => $a->is_active)->values()->map(fn($a) => [
                'id' => $a->id,
                'label' => $a->label,
                'masked_key' => $a->masked_api_key,
            ]),
            'supportedPairs' => BinanceConstants::SUPPORTED_PAIRS,
            'leverageOptions' => BinanceConstants::LEVERAGE_OPTIONS,
            'gridLimits' => [
                'min' => GridConstants::MIN_GRIDS,
                'max' => GridConstants::MAX_GRIDS,
                'min_leverage' => GridConstants::MIN_LEVERAGE,
                'max_leverage' => GridConstants::MAX_LEVERAGE,
                'min_investment' => GridConstants::MIN_INVESTMENT,
                'recommended_slippage' => GridConstants::RECOMMENDED_SLIPPAGE,
            ],
            'sides' => collect(BotSide::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'activeBotOrders' => $botOrders,
            'activeBotConfig' => $activeBot ? [
                'price_lower' => (float) $activeBot->price_lower,
                'price_upper' => (float) $activeBot->price_upper,
                'grid_count' => $activeBot->grid_count,
                'side' => $activeBot->side->value,
                'symbol' => $activeBot->symbol,
            ] : null,
        ]);
    }

    /**
     * Store a new bot.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'binance_account_id' => 'required|exists:binance_accounts,id',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|in:' . implode(',', BinanceConstants::SUPPORTED_PAIRS),
            'side' => 'required|string|in:' . implode(',', array_column(BotSide::cases(), 'value')),
            'price_lower' => 'required|numeric|min:0',
            'price_upper' => 'required|numeric|gt:price_lower',
            'grid_count' => 'required|integer|min:' . GridConstants::MIN_GRIDS . '|max:' . GridConstants::MAX_GRIDS,
            'investment' => 'required|numeric|min:' . GridConstants::MIN_INVESTMENT,
            'leverage' => 'required|integer|min:' . GridConstants::MIN_LEVERAGE . '|max:' . GridConstants::MAX_LEVERAGE,
            'slippage' => 'nullable|numeric|min:0|max:5',
            'stop_loss_price' => 'nullable|numeric|min:0',
            'take_profit_price' => 'nullable|numeric|min:0',
            'grid_mode' => 'nullable|string|in:arithmetic,geometric',
        ]);

        $validated['grid_mode'] = $validated['grid_mode'] ?? 'arithmetic';
        $validated['user_id'] = $request->user()->id;

        $bot = $this->botService->createBot($validated);

        return redirect()->route('bots.show', $bot->id)
            ->with('success', 'Bot creado exitosamente');
    }

    /**
     * Show bot details.
     */
    public function show(Request $request, Bot $bot): Response
    {
        $this->authorizeBot($request, $bot);

        $summary = $this->botService->getBotSummary($bot);
        $orders = $this->orderRepository->getByBot($bot->id);
        $pnlHistory = $this->pnlService->getHistoricalPnl($bot->id);

        $position = null;
        if (
            $bot->status === BotStatus::Active
            && $bot->binanceAccount
            && $bot->binanceAccount->api_key
            && $bot->binanceAccount->api_secret
        ) {
            try {
                $positions = $this->binanceApiService->getPositions($bot->binanceAccount, $bot->symbol);
                $position = !empty($positions) ? $positions[0] : null;
            } catch (\Exception $e) {
                // Position fetch is non-critical
            }
        }

        $lastOrderAt = $bot->orders()->latest('updated_at')->value('updated_at');
        $activeOrdersCount = $bot->orders()->where('status', 'open')->count();
        $filledToday = $bot->orders()
            ->where('status', 'filled')
            ->where('filled_at', '>=', now()->subDay())
            ->count();

        $chartOrders = $bot->orders()
            ->whereIn('status', ['open', 'filled'])
            ->orderBy('price')
            ->get(['id', 'side', 'status', 'price', 'quantity', 'filled_at', 'created_at'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'side' => $o->side->value,
                'status' => $o->status->value,
                'price' => (float) $o->price,
                'quantity' => (float) $o->quantity,
                'time' => ($o->filled_at ?? $o->created_at)?->timestamp,
                'created_at_fmt' => $o->created_at?->format('d/m H:i'),
                'filled_at_fmt' => $o->filled_at?->format('d/m H:i'),
            ])
            ->values();

        return Inertia::render('Bots/Show', [
            'bot' => $summary['bot'],
            'orderStats' => $summary['order_stats'],
            'gridConfig' => $summary['grid_config'],
            'orders' => $orders,
            'pnlHistory' => $pnlHistory,
            'position' => $position,
            'activity' => [
                'last_order_at' => $lastOrderAt?->toIso8601String(),
                'active_orders' => $activeOrdersCount,
                'filled_24h' => $filledToday,
                'rounds_24h' => (int) floor($filledToday / 2),
            ],
            'chartOrders' => $chartOrders,
        ]);
    }

    /**
     * Start a bot: dispatches the initialization job to place orders on Binance.
     */
    public function start(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        if ($bot->status === BotStatus::Active) {
            return back()->with('warning', 'El bot ya está activo');
        }

        $this->botService->startBot($bot);

        return back()->with('success', 'Bot en proceso de inicialización. Las órdenes se colocarán en breve.');
    }

    /**
     * Stop a bot: cancels all open orders on Binance and stops processing.
     */
    public function stop(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        if ($bot->status !== BotStatus::Active) {
            return back()->with('warning', 'El bot no está activo');
        }

        $this->botService->stopBot($bot);

        return back()->with('success', 'Bot detenido. Todas las órdenes abiertas fueron canceladas.');
    }

    /**
     * Calculate grid preview (AJAX).
     */
    public function calculateGrid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'price_lower' => 'required|numeric|min:0',
            'price_upper' => 'required|numeric|gt:price_lower',
            'grid_count' => 'required|integer|min:' . GridConstants::MIN_GRIDS . '|max:' . GridConstants::MAX_GRIDS,
            'investment' => 'required|numeric|min:' . GridConstants::MIN_INVESTMENT,
            'leverage' => 'required|integer|min:1|max:125',
            'side' => 'required|string|in:long,short,neutral',
            'grid_mode' => 'nullable|string|in:arithmetic,geometric',
        ]);

        $validated['grid_mode'] = $validated['grid_mode'] ?? 'arithmetic';
        $config = $this->gridCalculator->calculateFullGridConfig($validated);

        return $this->successResponse($config);
    }

    /**
     * Get current price of a symbol (AJAX).
     */
    public function currentPrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => 'required|string|in:' . implode(',', BinanceConstants::SUPPORTED_PAIRS),
        ]);

        $price = $this->binanceApiService->getCurrentPrice($validated['symbol']);

        if ($price === null) {
            return $this->errorResponse('No se pudo obtener el precio actual');
        }

        return $this->successResponse(['price' => $price]);
    }

    /**
     * Edit form for a bot (renders the trading page in edit mode).
     */
    public function edit(Request $request, Bot $bot): Response
    {
        $this->authorizeBot($request, $bot);

        $bots = Bot::where('user_id', $request->user()->id)
            ->withCount([
                'orders as open_orders_count' => fn ($q) => $q->where('status', 'open'),
                'orders as filled_orders_count' => fn ($q) => $q->where('status', 'filled'),
            ])
            ->orderByDesc('created_at')
            ->get();
        $accounts = $this->binanceAccountRepository->getByUser($request->user()->id);

        $botOrders = $bot->orders()
            ->whereIn('status', ['open', 'filled'])
            ->orderBy('price')
            ->get(['id', 'side', 'status', 'price', 'quantity', 'filled_at', 'created_at'])
            ->map(fn ($o) => [
                'id' => $o->id,
                'side' => $o->side->value,
                'status' => $o->status->value,
                'price' => (float) $o->price,
                'quantity' => (float) $o->quantity,
                'time' => ($o->filled_at ?? $o->created_at)?->timestamp,
                'created_at_fmt' => $o->created_at?->format('d/m H:i'),
                'filled_at_fmt' => $o->filled_at?->format('d/m H:i'),
            ])
            ->values();

        return Inertia::render('Bots/Index', [
            'bots' => $bots,
            'binanceAccounts' => $accounts->filter(fn($a) => $a->is_active)->values()->map(fn($a) => [
                'id' => $a->id,
                'label' => $a->label,
                'masked_key' => $a->masked_api_key,
            ]),
            'supportedPairs' => BinanceConstants::SUPPORTED_PAIRS,
            'leverageOptions' => BinanceConstants::LEVERAGE_OPTIONS,
            'gridLimits' => [
                'min' => GridConstants::MIN_GRIDS,
                'max' => GridConstants::MAX_GRIDS,
                'min_leverage' => GridConstants::MIN_LEVERAGE,
                'max_leverage' => GridConstants::MAX_LEVERAGE,
                'min_investment' => GridConstants::MIN_INVESTMENT,
                'recommended_slippage' => GridConstants::RECOMMENDED_SLIPPAGE,
            ],
            'sides' => collect(BotSide::cases())->map(fn($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ]),
            'activeBotOrders' => $botOrders,
            'activeBotConfig' => [
                'price_lower' => (float) $bot->price_lower,
                'price_upper' => (float) $bot->price_upper,
                'grid_count' => $bot->grid_count,
                'side' => $bot->side->value,
                'symbol' => $bot->symbol,
            ],
            'editBot' => [
                'id' => $bot->id,
                'binance_account_id' => (string) $bot->binance_account_id,
                'name' => $bot->name,
                'symbol' => $bot->symbol,
                'side' => $bot->side->value,
                'price_lower' => (string) $bot->price_lower,
                'price_upper' => (string) $bot->price_upper,
                'grid_count' => (string) $bot->grid_count,
                'investment' => (string) $bot->investment,
                'leverage' => (string) $bot->leverage,
                'slippage' => (string) $bot->slippage,
                'stop_loss_price' => $bot->stop_loss_price ? (string) $bot->stop_loss_price : '',
                'take_profit_price' => $bot->take_profit_price ? (string) $bot->take_profit_price : '',
                'grid_mode' => $bot->grid_mode ?? 'arithmetic',
                'status' => $bot->status->value,
            ],
        ]);
    }

    /**
     * Update bot configuration. If bot is active, it will be stopped,
     * reconfigured and restarted automatically.
     */
    public function update(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price_lower' => 'required|numeric|min:0',
            'price_upper' => 'required|numeric|gt:price_lower',
            'grid_count' => 'required|integer|min:' . GridConstants::MIN_GRIDS . '|max:' . GridConstants::MAX_GRIDS,
            'investment' => 'required|numeric|min:' . GridConstants::MIN_INVESTMENT,
            'leverage' => 'required|integer|min:' . GridConstants::MIN_LEVERAGE . '|max:' . GridConstants::MAX_LEVERAGE,
            'slippage' => 'nullable|numeric|min:0|max:5',
            'stop_loss_price' => 'nullable|numeric|min:0',
            'take_profit_price' => 'nullable|numeric|min:0',
            'grid_mode' => 'nullable|string|in:arithmetic,geometric',
        ]);

        $validated['grid_mode'] = $validated['grid_mode'] ?? $bot->grid_mode ?? 'arithmetic';
        $wasActive = $bot->status === BotStatus::Active;

        if ($wasActive) {
            $this->botService->stopBot($bot);
            $bot->refresh();
        }

        $gridConfig = $this->gridCalculator->calculateFullGridConfig([
            'price_lower' => $validated['price_lower'],
            'price_upper' => $validated['price_upper'],
            'grid_count' => $validated['grid_count'],
            'investment' => $validated['investment'],
            'leverage' => $validated['leverage'],
            'side' => $bot->side->value,
            'grid_mode' => $validated['grid_mode'],
        ]);

        $bot->update(array_merge($validated, [
            'real_investment' => $gridConfig['real_investment'],
            'additional_margin' => $gridConfig['additional_margin'],
            'est_liquidation_price' => $gridConfig['est_liquidation_price'],
            'profit_per_grid' => $gridConfig['profit_per_grid'],
            'commission_per_grid' => $gridConfig['commission_per_grid'],
        ]));

        if ($wasActive) {
            $this->botService->startBot($bot);

            return redirect()->route('bots.show', $bot->id)
                ->with('success', 'Bot actualizado y reiniciado. Las nuevas órdenes se colocarán en breve.');
        }

        return redirect()->route('bots.show', $bot->id)
            ->with('success', 'Bot actualizado exitosamente');
    }

    /**
     * Delete a bot.
     */
    public function destroy(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        $this->botService->deleteBot($bot);

        return redirect()->route('bots.index')
            ->with('success', 'Bot eliminado');
    }

    /**
     * Verify bot ownership.
     */
    private function authorizeBot(Request $request, Bot $bot): void
    {
        abort_if($bot->user_id !== $request->user()->id, 403);
    }
}
