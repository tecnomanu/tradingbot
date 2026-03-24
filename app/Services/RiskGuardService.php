<?php

namespace App\Services;

use App\Enums\BotStatus;
use App\Models\BinanceAccount;
use App\Models\Bot;
use App\Models\BotPnlSnapshot;
use App\Repositories\BotRepository;
use Illuminate\Support\Facades\Cache;
use App\Support\BotLog as Log;

class RiskGuardService
{
    public const DEFAULTS = [
        'max_drawdown_pct' => 10.0,            // legacy — ignored when v2 keys present
        'soft_guard_drawdown_pct' => 15.0,
        'hard_guard_drawdown_pct' => 20.0,
        'hard_guard_action' => 'stop_bot_only', // stop_bot_only | close_position_and_stop | pause_and_rebuild | notify_only
        'drawdown_mode' => 'peak_equity_drawdown', // peak_equity_drawdown | initial_capital_loss
        'min_liquidation_distance_pct' => 15.0,
        'max_price_out_of_range_pct' => 5.0,
        'max_consecutive_errors' => 5,
        'max_grid_rebuilds_per_hour' => 3,
        'emergency_stop' => false,
    ];

    public function __construct(
        private BotRepository $botRepository,
        private BinanceFuturesService $binance,
        private TelegramService $telegram,
    ) {}

    // ── Public API ──────────────────────────────────────────────────────

    /**
     * Run all risk checks. Returns null if safe, or an array with level + reason.
     *
     * @return array{level: 'soft'|'hard', reason: string}|null
     */
    public function evaluate(Bot $bot): ?array
    {
        $config = $this->getEffectiveConfig($bot);

        $hardChecks = [
            fn () => $this->checkEmergencyStop($config),
            fn () => $this->checkHardDrawdown($bot, $config),
            fn () => $this->checkLiquidationDistance($bot, $config),
            fn () => $this->checkConsecutiveErrors($bot, $config),
            fn () => $this->checkGridRebuilds($bot, $config),
        ];

        foreach ($hardChecks as $check) {
            $reason = $check();
            if ($reason !== null) {
                return ['level' => 'hard', 'reason' => $reason];
            }
        }

        $softChecks = [
            fn () => $this->checkSoftDrawdown($bot, $config),
            fn () => $this->checkPriceOutOfRange($bot, $config),
        ];

        foreach ($softChecks as $check) {
            $reason = $check();
            if ($reason !== null) {
                return ['level' => 'soft', 'reason' => $reason];
            }
        }

        return null;
    }

    /**
     * Run evaluation and apply the appropriate guard level.
     * Returns true if the bot was stopped (hard guard), false otherwise.
     */
    public function guard(Bot $bot): bool
    {
        $result = $this->evaluate($bot);

        if ($result === null) {
            $this->clearSoftGuard($bot);
            return false;
        }

        $level = $result['level'];
        $reason = $result['reason'];
        $config = $this->getEffectiveConfig($bot);

        if ($level === 'soft') {
            return $this->applySoftGuard($bot, $reason, $config);
        }

        return $this->applyHardGuard($bot, $reason, $config);
    }

    public function recordError(Bot $bot): void
    {
        $key = "risk_guard.errors.{$bot->id}";
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }

    public function clearErrors(Bot $bot): void
    {
        Cache::forget("risk_guard.errors.{$bot->id}");
    }

    public function recordGridRebuild(Bot $bot): void
    {
        $key = "risk_guard.rebuilds.{$bot->id}";
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }

    /**
     * @return array<string, mixed>
     */
    public function getEffectiveConfig(Bot $bot): array
    {
        $config = array_merge(self::DEFAULTS, $bot->risk_config ?? []);

        // Backward compatibility: if bot only has legacy max_drawdown_pct but no v2 keys,
        // derive soft/hard from the old single value.
        if ($bot->risk_config !== null
            && isset($bot->risk_config['max_drawdown_pct'])
            && !isset($bot->risk_config['soft_guard_drawdown_pct'])
        ) {
            $legacy = (float) $bot->risk_config['max_drawdown_pct'];
            $config['soft_guard_drawdown_pct'] = $legacy;
            $config['hard_guard_drawdown_pct'] = $legacy + 5.0;
        }

        return $config;
    }

    // ── Soft Guard ──────────────────────────────────────────────────────

    /**
     * Soft guard: keep bot active, enter protection mode.
     * Returns false (bot NOT stopped).
     */
    private function applySoftGuard(Bot $bot, string $reason, array $config): bool
    {
        if ($bot->risk_guard_level === 'soft') {
            return false; // already in soft guard, don't spam
        }

        Log::warning('RiskGuard: soft guard triggered — entering protection mode', [
            'bot_id' => $bot->id,
            'reason' => $reason,
        ]);

        $beforeState = BotActivityLogger::captureState($bot);

        BotActivityLogger::logSystemAction($bot, 'soft_guard_triggered', [
            'reason' => $reason,
            'level' => 'soft',
            'drawdown_mode' => $config['drawdown_mode'] ?? 'peak_equity_drawdown',
            'threshold_pct' => $config['soft_guard_drawdown_pct'],
            'config' => $config,
        ], $beforeState);

        $this->botRepository->update($bot, [
            'risk_guard_level' => 'soft',
            'risk_guard_reason' => $reason,
            'risk_guard_triggered_at' => now(),
        ]);

        $this->notifyTelegram($bot, $reason, 'soft', $config);

        return false;
    }

    private function clearSoftGuard(Bot $bot): void
    {
        if ($bot->risk_guard_level !== 'soft') {
            return;
        }

        Log::info('RiskGuard: soft guard cleared — conditions improved', ['bot_id' => $bot->id]);

        BotActivityLogger::logSystemAction($bot, 'soft_guard_cleared', [
            'previous_reason' => $bot->risk_guard_reason,
        ]);

        $this->botRepository->update($bot, [
            'risk_guard_level' => null,
            'risk_guard_reason' => null,
            'risk_guard_triggered_at' => null,
        ]);
    }

    // ── Hard Guard ──────────────────────────────────────────────────────

    /**
     * Hard guard: execute the configured action (stop, close+stop, etc).
     * Returns true if bot was stopped.
     */
    private function applyHardGuard(Bot $bot, string $reason, array $config): bool
    {
        $action = $config['hard_guard_action'] ?? 'stop_bot_only';

        Log::warning('RiskGuard: hard guard triggered', [
            'bot_id' => $bot->id,
            'reason' => $reason,
            'action' => $action,
        ]);

        $beforeState = BotActivityLogger::captureState($bot);

        BotActivityLogger::logSystemAction($bot, 'hard_guard_triggered', [
            'reason' => $reason,
            'level' => 'hard',
            'action' => $action,
            'drawdown_mode' => $config['drawdown_mode'] ?? 'peak_equity_drawdown',
            'threshold_pct' => $config['hard_guard_drawdown_pct'],
            'config' => $config,
        ], $beforeState);

        $stopped = match ($action) {
            'close_position_and_stop' => $this->actionCloseAndStop($bot, $reason),
            'notify_only' => $this->actionNotifyOnly($bot, $reason),
            'pause_and_rebuild' => $this->actionPauseAndRebuild($bot, $reason),
            default => $this->actionStopOnly($bot, $reason),
        };

        $this->notifyTelegram($bot, $reason, 'hard', $config);

        return $stopped;
    }

    private function actionStopOnly(Bot $bot, string $reason): bool
    {
        $this->botRepository->update($bot, [
            'status' => BotStatus::Stopped,
            'stopped_at' => now(),
            'stop_reason' => 'risk_guard',
            'risk_guard_level' => 'hard',
            'risk_guard_reason' => $reason,
            'risk_guard_triggered_at' => now(),
            'last_error_message' => "Risk Guard: {$reason}",
        ]);
        return true;
    }

    private function actionCloseAndStop(Bot $bot, string $reason): bool
    {
        $account = $bot->binanceAccount;
        if ($account instanceof BinanceAccount) {
            try {
                $this->binance->cancelAllOrders($account, $bot->symbol);

                $positions = $this->binance->getPositions($account, $bot->symbol);
                foreach ($positions as $pos) {
                    $amt = (float) ($pos['positionAmt'] ?? 0);
                    if ($amt != 0) {
                        $closeSide = $amt > 0 ? 'SELL' : 'BUY';
                        $this->binance->placeMarketOrder($account, $bot->symbol, $closeSide, abs($amt));
                    }
                }
            } catch (\Exception $e) {
                Log::error('RiskGuard: close_position_and_stop partial failure', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->botRepository->update($bot, [
            'status' => BotStatus::Stopped,
            'stopped_at' => now(),
            'stop_reason' => 'risk_guard',
            'risk_guard_level' => 'hard',
            'risk_guard_reason' => $reason,
            'risk_guard_triggered_at' => now(),
            'last_error_message' => "Risk Guard (close+stop): {$reason}",
        ]);
        return true;
    }

    private function actionPauseAndRebuild(Bot $bot, string $reason): bool
    {
        $account = $bot->binanceAccount;
        if ($account instanceof BinanceAccount) {
            try {
                $this->binance->cancelAllOrders($account, $bot->symbol);
            } catch (\Exception $e) {
                Log::error('RiskGuard: pause_and_rebuild cancel failed', [
                    'bot_id' => $bot->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->botRepository->update($bot, [
            'status' => BotStatus::Stopped,
            'stopped_at' => now(),
            'stop_reason' => 'risk_guard',
            'risk_guard_level' => 'hard',
            'risk_guard_reason' => $reason,
            'risk_guard_triggered_at' => now(),
            'reentry_enabled' => true,
            'last_error_message' => "Risk Guard (pause+rebuild): {$reason}",
        ]);
        return true;
    }

    private function actionNotifyOnly(Bot $bot, string $reason): bool
    {
        $this->botRepository->update($bot, [
            'risk_guard_level' => 'hard',
            'risk_guard_reason' => $reason,
            'risk_guard_triggered_at' => now(),
            'last_error_message' => "Risk Guard (notify): {$reason}",
        ]);
        return false; // bot stays active
    }

    // ── Drawdown checks ─────────────────────────────────────────────────

    private function checkSoftDrawdown(Bot $bot, array $config): ?string
    {
        $softPct = (float) ($config['soft_guard_drawdown_pct'] ?? 15.0);
        $hardPct = (float) ($config['hard_guard_drawdown_pct'] ?? 20.0);
        if ($softPct <= 0) {
            return null;
        }

        $drawdownPct = $this->calculateDrawdown($bot, $config);
        if ($drawdownPct === null) {
            return null;
        }

        $mode = $config['drawdown_mode'] ?? 'peak_equity_drawdown';
        $modeLabel = $mode === 'initial_capital_loss' ? 'pérdida s/ capital' : 'drawdown desde pico';

        if ($drawdownPct >= $softPct && $drawdownPct < $hardPct) {
            return "Soft guard: {$this->fmt($drawdownPct)}% {$modeLabel} (límite soft: {$softPct}%)";
        }

        return null;
    }

    private function checkHardDrawdown(Bot $bot, array $config): ?string
    {
        $hardPct = (float) ($config['hard_guard_drawdown_pct'] ?? 20.0);
        if ($hardPct <= 0) {
            return null;
        }

        $drawdownPct = $this->calculateDrawdown($bot, $config);
        if ($drawdownPct === null) {
            return null;
        }

        $mode = $config['drawdown_mode'] ?? 'peak_equity_drawdown';
        $modeLabel = $mode === 'initial_capital_loss' ? 'pérdida s/ capital' : 'drawdown desde pico';

        if ($drawdownPct >= $hardPct) {
            return "Hard guard: {$this->fmt($drawdownPct)}% {$modeLabel} (límite hard: {$hardPct}%)";
        }

        return null;
    }

    /**
     * Calculate drawdown percentage using the configured mode.
     */
    private function calculateDrawdown(Bot $bot, array $config): ?float
    {
        $mode = $config['drawdown_mode'] ?? 'peak_equity_drawdown';

        if ($mode === 'initial_capital_loss') {
            return $this->calcInitialCapitalLoss($bot);
        }

        return $this->calcPeakEquityDrawdown($bot);
    }

    private function calcPeakEquityDrawdown(Bot $bot): ?float
    {
        $peakPnl = BotPnlSnapshot::where('bot_id', $bot->id)->max('total_pnl');
        if ($peakPnl === null) {
            return null;
        }

        $peakPnl = (float) $peakPnl;
        $currentPnl = (float) $bot->total_pnl;
        $investment = (float) $bot->real_investment;
        $equityAtPeak = $investment + $peakPnl;

        if ($equityAtPeak <= 0) {
            return null;
        }

        return (($peakPnl - $currentPnl) / $equityAtPeak) * 100;
    }

    private function calcInitialCapitalLoss(Bot $bot): ?float
    {
        $investment = (float) $bot->real_investment;
        if ($investment <= 0) {
            return null;
        }

        $currentPnl = (float) $bot->total_pnl;
        if ($currentPnl >= 0) {
            return 0.0;
        }

        return (abs($currentPnl) / $investment) * 100;
    }

    // ── Other checks (unchanged logic, promoted to hard) ────────────────

    private function checkEmergencyStop(array $config): ?string
    {
        if (!empty($config['emergency_stop'])) {
            return 'Emergency stop activado manualmente';
        }
        return null;
    }

    private function checkLiquidationDistance(Bot $bot, array $config): ?string
    {
        $threshold = (float) $config['min_liquidation_distance_pct'];
        if ($threshold <= 0) {
            return null;
        }

        $account = $bot->binanceAccount;
        if (!$account instanceof BinanceAccount) {
            return null;
        }

        try {
            $positions = $this->binance->getPositions($account, $bot->symbol);
        } catch (\Exception) {
            return null;
        }

        foreach ($positions as $pos) {
            $markPrice = (float) ($pos['markPrice'] ?? 0);
            $liqPrice = (float) ($pos['liquidationPrice'] ?? 0);
            $posAmt = (float) ($pos['positionAmt'] ?? 0);

            if ($markPrice <= 0 || $liqPrice <= 0 || $posAmt == 0) {
                continue;
            }

            $distancePct = abs(($markPrice - $liqPrice) / $markPrice) * 100;

            if ($distancePct < $threshold) {
                return "Distancia a liquidación muy baja: {$this->fmt($distancePct)}% (mínimo: {$threshold}%)";
            }
        }

        return null;
    }

    private function checkPriceOutOfRange(Bot $bot, array $config): ?string
    {
        $threshold = (float) $config['max_price_out_of_range_pct'];
        if ($threshold <= 0) {
            return null;
        }

        $account = $bot->binanceAccount;
        if (!$account instanceof BinanceAccount) {
            return null;
        }

        try {
            $currentPrice = $this->binance->getCurrentPrice($account, $bot->symbol);
        } catch (\Exception) {
            return null;
        }

        if (!$currentPrice) {
            return null;
        }

        $lower = (float) $bot->price_lower;
        $upper = (float) $bot->price_upper;

        if ($currentPrice < $lower) {
            $deviationPct = (($lower - $currentPrice) / $lower) * 100;
            if ($deviationPct > $threshold) {
                return "Precio {$this->fmt($deviationPct)}% por debajo del rango inferior (límite: {$threshold}%)";
            }
        } elseif ($currentPrice > $upper) {
            $deviationPct = (($currentPrice - $upper) / $upper) * 100;
            if ($deviationPct > $threshold) {
                return "Precio {$this->fmt($deviationPct)}% por encima del rango superior (límite: {$threshold}%)";
            }
        }

        return null;
    }

    private function checkConsecutiveErrors(Bot $bot, array $config): ?string
    {
        $max = (int) $config['max_consecutive_errors'];
        if ($max <= 0) {
            return null;
        }

        $count = (int) Cache::get("risk_guard.errors.{$bot->id}", 0);
        if ($count >= $max) {
            return "Errores consecutivos del exchange: {$count} (límite: {$max})";
        }
        return null;
    }

    private function checkGridRebuilds(Bot $bot, array $config): ?string
    {
        $max = (int) $config['max_grid_rebuilds_per_hour'];
        if ($max <= 0) {
            return null;
        }

        $count = (int) Cache::get("risk_guard.rebuilds.{$bot->id}", 0);
        if ($count >= $max) {
            return "Exceso de rebuilds de grid: {$count} en última hora (límite: {$max})";
        }
        return null;
    }

    // ── Telegram ────────────────────────────────────────────────────────

    private function notifyTelegram(Bot $bot, string $reason, string $level, array $config): void
    {
        if (!$bot->ai_notify_telegram) {
            return;
        }

        $events = $bot->ai_notify_events ?? [];
        $matchEvents = $level === 'soft'
            ? ['soft_guard_triggered', 'risk_guard_triggered']
            : ['hard_guard_triggered', 'risk_guard_triggered', 'bot_stopped'];

        if (empty(array_intersect($matchEvents, $events))) {
            return;
        }

        $user = $bot->user;
        if (!$user || empty($user->telegram_chat_id)) {
            return;
        }

        try {
            $message = $this->telegram->formatRiskGuardNotification(
                $bot->name,
                $bot->symbol,
                $reason,
                $level,
                $config['hard_guard_action'] ?? 'stop_bot_only',
                $config['drawdown_mode'] ?? 'peak_equity_drawdown',
            );
            $this->telegram->sendMessage($user->telegram_chat_id, $message);
        } catch (\Exception $e) {
            Log::warning('RiskGuard: telegram notification failed', [
                'bot_id' => $bot->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function fmt(float $val): string
    {
        return number_format($val, 2);
    }
}
