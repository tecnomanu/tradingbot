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
    /**
     * Default risk thresholds applied when bot has no custom risk_config.
     */
    public const DEFAULTS = [
        'max_drawdown_pct' => 10.0,
        'min_liquidation_distance_pct' => 15.0,
        'max_price_out_of_range_pct' => 5.0,
        'max_consecutive_errors' => 5,
        'max_grid_rebuilds_per_hour' => 3,
        'emergency_stop' => false,
    ];

    public function __construct(
        private BotRepository $botRepository,
        private BinanceFuturesService $binance,
    ) {}

    /**
     * Run all risk checks. Returns null if safe, or the violation reason string.
     */
    public function evaluate(Bot $bot): ?string
    {
        $config = array_merge(self::DEFAULTS, $bot->risk_config ?? []);

        $checks = [
            fn () => $this->checkEmergencyStop($config),
            fn () => $this->checkMaxDrawdown($bot, $config),
            fn () => $this->checkLiquidationDistance($bot, $config),
            fn () => $this->checkPriceOutOfRange($bot, $config),
            fn () => $this->checkConsecutiveErrors($bot, $config),
            fn () => $this->checkGridRebuilds($bot, $config),
        ];

        foreach ($checks as $check) {
            $reason = $check();
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * Run evaluation and stop the bot if a rule triggers.
     * Returns true if the bot was stopped.
     */
    public function guard(Bot $bot): bool
    {
        $reason = $this->evaluate($bot);

        if ($reason === null) {
            return false;
        }

        Log::warning('RiskGuard: rule triggered — stopping bot', [
            'bot_id' => $bot->id,
            'reason' => $reason,
        ]);

        $beforeState = BotActivityLogger::captureState($bot);

        BotActivityLogger::logSystemAction($bot, 'risk_guard_triggered', [
            'reason' => $reason,
            'config' => array_merge(self::DEFAULTS, $bot->risk_config ?? []),
        ], $beforeState);

        $this->botRepository->update($bot, [
            'status' => BotStatus::Stopped,
            'stopped_at' => now(),
            'risk_guard_reason' => $reason,
            'risk_guard_triggered_at' => now(),
            'last_error_message' => "Risk Guard: {$reason}",
        ]);

        return true;
    }

    /**
     * Increment the consecutive error counter for a bot.
     * Called from GridTradingEngine when processBot catches an exception.
     */
    public function recordError(Bot $bot): void
    {
        $key = "risk_guard.errors.{$bot->id}";
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }

    /**
     * Reset the error counter on successful cycle.
     */
    public function clearErrors(Bot $bot): void
    {
        Cache::forget("risk_guard.errors.{$bot->id}");
    }

    /**
     * Increment grid rebuild counter.
     */
    public function recordGridRebuild(Bot $bot): void
    {
        $key = "risk_guard.rebuilds.{$bot->id}";
        $count = (int) Cache::get($key, 0);
        Cache::put($key, $count + 1, now()->addHour());
    }

    /**
     * Get the merged config (defaults + overrides) for display.
     *
     * @return array<string, mixed>
     */
    public function getEffectiveConfig(Bot $bot): array
    {
        return array_merge(self::DEFAULTS, $bot->risk_config ?? []);
    }

    // ── Individual checks ──────────────────────────────────────────────

    private function checkEmergencyStop(array $config): ?string
    {
        if (!empty($config['emergency_stop'])) {
            return 'Emergency stop activado manualmente';
        }
        return null;
    }

    private function checkMaxDrawdown(Bot $bot, array $config): ?string
    {
        $threshold = (float) $config['max_drawdown_pct'];
        if ($threshold <= 0) {
            return null;
        }

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

        $drawdownPct = (($peakPnl - $currentPnl) / $equityAtPeak) * 100;

        if ($drawdownPct >= $threshold) {
            return "Max drawdown alcanzado: {$this->fmt($drawdownPct)}% (límite: {$threshold}%)";
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

    private function fmt(float $val): string
    {
        return number_format($val, 2);
    }
}
