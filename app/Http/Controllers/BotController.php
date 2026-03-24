<?php

namespace App\Http\Controllers;

use App\Constants\BinanceConstants;
use App\Constants\GridConstants;
use App\Enums\BotSide;
use App\Enums\BotStatus;
use App\Http\Requests\StoreBotRequest;
use App\Http\Requests\UpdateBotRequest;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Repositories\BinanceAccountRepository;
use App\Repositories\BotRepository;
use App\Repositories\OrderRepository;
use App\Services\BinanceApiService;
use App\Services\BotActivityLogger;
use App\Services\BotService;
use App\Services\GridCalculatorService;
use App\Services\AgentImpactService;
use App\Services\PnlService;
use App\Services\RiskGuardService;
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
        private RiskGuardService $riskGuardService,
        private AgentImpactService $agentImpactService,
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
    public function store(StoreBotRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['grid_mode'] = $validated['grid_mode'] ?? 'arithmetic';
        $validated['user_id'] = $request->user()->id;

        $validated = $this->extractRiskGuardConfig($validated);

        $bot = $this->botService->createBot($validated);

        BotActivityLogger::logUserAction($bot, 'bot_created', $request->user(), [
            'symbol' => $bot->symbol,
            'side' => $bot->side->value,
            'grid_count' => $bot->grid_count,
            'investment' => (float) $bot->investment,
        ]);

        return redirect()->route('bots.show', $bot->id)
            ->with('success', 'Bot creado exitosamente');
    }

    /**
     * Show bot details.
     */
    public function show(Request $request, Bot $bot): Response
    {
        $this->authorizeBot($request, $bot);

        $bot->loadCount([
            'orders as open_orders_count' => fn ($q) => $q->where('status', 'open'),
            'orders as filled_24h_count' => fn ($q) => $q->where('status', 'filled')
                ->where('filled_at', '>=', now()->subDay()),
        ]);

        $summary = $this->botService->getBotSummary($bot);
        $orders = $this->orderRepository->getByBot($bot->id);
        $pnlHistory = $this->pnlService->getHistoricalPnl($bot->id);
        $drawdown = $this->pnlService->calculateDrawdown($bot);

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
        $activeOrdersCount = (int) ($bot->open_orders_count ?? 0);
        $filledToday = (int) ($bot->filled_24h_count ?? 0);

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

        $recentFills = $this->orderRepository->getFilledByBot($bot->id)
            ->take(50)
            ->map(fn ($o) => [
                'id' => $o->id,
                'side' => $o->side->value,
                'price' => (float) $o->price,
                'quantity' => (float) $o->quantity,
                'pnl' => (float) ($o->pnl ?? 0),
                'fee' => isset($o->fee) ? (float) $o->fee : null,
                'filled_at' => $o->filled_at?->toIso8601String(),
                'filled_at_fmt' => $o->filled_at?->format('d/m H:i'),
            ])
            ->values();

        $activityLogs = BotActionLog::where('bot_id', $bot->id)
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (BotActionLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'source' => $log->source,
                'actor_label' => $log->actor_label,
                'details' => $log->details,
                'before_state' => $log->before_state,
                'after_state' => $log->after_state,
                'result' => $log->result ?? 'success',
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->toIso8601String(),
                'created_at_fmt' => $log->created_at->format('d/m H:i:s'),
            ]);

        $since24h = now()->subDay();
        $logsQuery = BotActionLog::where('bot_id', $bot->id);

        $lastError = (clone $logsQuery)->where('result', 'failed')
            ->orderByDesc('created_at')->first(['action', 'error_message', 'created_at']);

        $lastAgentAction = (clone $logsQuery)->where('source', 'agent')
            ->orderByDesc('created_at')->first(['action', 'details', 'created_at']);

        $health = [
            'last_sync_at' => $lastOrderAt?->toIso8601String(),
            'last_error' => $lastError ? [
                'action' => $lastError->action,
                'message' => mb_substr((string) $lastError->error_message, 0, 120),
                'at' => $lastError->created_at->toIso8601String(),
            ] : null,
            'errors_24h' => (clone $logsQuery)->where('result', 'failed')
                ->where('created_at', '>=', $since24h)->count(),
            'last_agent_action' => $lastAgentAction ? [
                'action' => $lastAgentAction->action,
                'at' => $lastAgentAction->created_at->toIso8601String(),
                'reason' => $lastAgentAction->details['reason'] ?? null,
            ] : null,
            'grid_adjusts_24h' => (clone $logsQuery)->where('action', 'grid_adjusted')
                ->where('created_at', '>=', $since24h)->count(),
            'sl_tp_changes_24h' => (clone $logsQuery)->whereIn('action', ['sl_set', 'tp_set'])
                ->where('created_at', '>=', $since24h)->count(),
            'cancellations_24h' => (clone $logsQuery)->where('action', 'orders_cancelled')
                ->where('created_at', '>=', $since24h)->count(),
            'last_error_message' => $bot->last_error_message,
        ];

        return Inertia::render('Bots/Show', [
            'bot' => $summary['bot'],
            'orderStats' => $summary['order_stats'],
            'gridConfig' => $summary['grid_config'],
            'orders' => $orders,
            'pnlHistory' => $pnlHistory,
            'drawdown' => $drawdown,
            'riskGuard' => [
                'effective_config' => $this->riskGuardService->getEffectiveConfig($bot),
                'is_triggered' => $bot->risk_guard_reason !== null,
                'level' => $bot->risk_guard_level,
                'reason' => $bot->risk_guard_reason,
                'triggered_at' => $bot->risk_guard_triggered_at?->toIso8601String(),
                'stop_reason' => $bot->stop_reason,
                'reentry_enabled' => $bot->reentry_enabled,
                'reentry_cooldown_minutes' => $bot->reentry_cooldown_minutes,
                'reentry_last_attempt_at' => $bot->reentry_last_attempt_at?->toIso8601String(),
                'reentry_last_block_reason' => $bot->reentry_last_block_reason,
            ],
            'position' => $position,
            'activity' => [
                'last_order_at' => $lastOrderAt?->toIso8601String(),
                'active_orders' => $activeOrdersCount,
                'filled_24h' => $filledToday,
                'rounds_24h' => (int) floor($filledToday / 2),
            ],
            'chartOrders' => $chartOrders,
            'recentFills' => $recentFills,
            'activityLogs' => $activityLogs,
            'health' => $health,
            'agentImpact' => $this->agentImpactService->compare($bot),
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

        $beforeState = BotActivityLogger::captureState($bot);
        $this->botService->startBot($bot);
        $bot->refresh();

        BotActivityLogger::logUserAction($bot, 'bot_started', $request->user(), [
            'reason' => 'user_action',
        ], $beforeState, BotActivityLogger::captureState($bot));

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

        $beforeState = BotActivityLogger::captureState($bot);
        $this->botService->stopBot($bot);
        $bot->refresh();

        $bot->update([
            'stop_reason' => 'manual',
            'risk_guard_level' => null,
            'risk_guard_reason' => null,
        ]);

        BotActivityLogger::logUserAction($bot, 'bot_stopped', $request->user(), [
            'reason' => 'user_action',
        ], $beforeState, BotActivityLogger::captureState($bot));

        return back()->with('success', 'Bot detenido. Todas las órdenes abiertas fueron canceladas.');
    }

    /**
     * Attempt manual re-entry for a bot stopped by risk guard.
     */
    public function attemptReentry(Request $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        if ($bot->status !== BotStatus::Stopped) {
            return back()->with('warning', 'El bot no está detenido');
        }

        if ($bot->stop_reason !== 'risk_guard') {
            return back()->with('warning', 'Solo se puede reingresar bots detenidos por Risk Guard');
        }

        $reentryService = app(\App\Services\ReentryService::class);
        $result = $reentryService->attemptReentry($bot, 'manual');

        if ($result['success']) {
            BotActivityLogger::logUserAction($bot, 'reentry_success', $request->user(), [
                'trigger' => 'manual',
                'reason' => $result['reason'],
            ]);
            return back()->with('success', 'Re-entry exitoso: ' . $result['reason']);
        }

        BotActivityLogger::logUserAction($bot, 'reentry_blocked', $request->user(), [
            'trigger' => 'manual',
            'reason' => $result['reason'],
        ]);
        return back()->with('warning', 'Re-entry bloqueado: ' . $result['reason']);
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
    public function update(UpdateBotRequest $request, Bot $bot): RedirectResponse
    {
        $this->authorizeBot($request, $bot);

        $validated = $request->validated();

        $validated['grid_mode'] = $validated['grid_mode'] ?? $bot->grid_mode ?? 'arithmetic';
        $validated = $this->extractRiskGuardConfig($validated, $bot);
        $wasActive = $bot->status === BotStatus::Active;
        $beforeState = BotActivityLogger::captureState($bot);

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

        $afterState = BotActivityLogger::captureState($bot);

        BotActivityLogger::logUserAction($bot, 'bot_updated', $request->user(), [
            'was_active' => $wasActive,
            'fields_changed' => array_keys($validated),
        ], $beforeState, $afterState);

        $rangeChanged = $beforeState['price_lower'] !== $afterState['price_lower']
            || $beforeState['price_upper'] !== $afterState['price_upper'];

        if ($rangeChanged) {
            BotActivityLogger::logUserAction($bot, 'grid_adjusted', $request->user(), [
                'reason' => 'manual_action',
                'old_range' => "{$beforeState['price_lower']}-{$beforeState['price_upper']}",
                'new_range' => "{$afterState['price_lower']}-{$afterState['price_upper']}",
            ], $beforeState, $afterState);
        }

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

        BotActivityLogger::logUserAction($bot, 'bot_deleted', $request->user(), [
            'symbol' => $bot->symbol,
            'name' => $bot->name,
        ]);

        $this->botService->deleteBot($bot);

        return redirect()->route('bots.index')
            ->with('success', 'Bot eliminado');
    }

    /**
     * Extract risk guard fields from validated data and merge into risk_config + separate columns.
     *
     * @return array<string, mixed>
     */
    private function extractRiskGuardConfig(array $validated, ?Bot $existingBot = null): array
    {
        $riskFields = ['drawdown_mode', 'soft_guard_drawdown_pct', 'hard_guard_drawdown_pct', 'hard_guard_action'];
        $riskConfig = $existingBot?->risk_config ?? [];
        $hasRiskData = false;

        foreach ($riskFields as $field) {
            if (isset($validated[$field]) && $validated[$field] !== '' && $validated[$field] !== null) {
                $riskConfig[$field] = is_numeric($validated[$field]) ? (float) $validated[$field] : $validated[$field];
                $hasRiskData = true;
            }
            unset($validated[$field]);
        }

        if ($hasRiskData) {
            $validated['risk_config'] = $riskConfig;
        }

        if (isset($validated['reentry_enabled'])) {
            $validated['reentry_enabled'] = (bool) $validated['reentry_enabled'];
        }

        if (isset($validated['reentry_cooldown_minutes']) && $validated['reentry_cooldown_minutes'] !== '') {
            $validated['reentry_cooldown_minutes'] = (int) $validated['reentry_cooldown_minutes'];
        } else {
            unset($validated['reentry_cooldown_minutes']);
        }

        return $validated;
    }

    /**
     * Verify bot ownership.
     */
    private function authorizeBot(Request $request, Bot $bot): void
    {
        abort_if($bot->user_id !== $request->user()->id, 403);
    }
}
