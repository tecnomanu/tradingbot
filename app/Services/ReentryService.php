<?php

namespace App\Services;

use App\Enums\BotStatus;
use App\Jobs\InitializeBotJob;
use App\Models\BinanceAccount;
use App\Models\Bot;
use App\Models\BotPnlSnapshot;
use App\Repositories\BotRepository;
use App\Support\BotLog as Log;
use Illuminate\Support\Facades\Cache;

class ReentryService
{
    public function __construct(
        private BotRepository $botRepository,
        private BinanceFuturesService $binance,
        private GridCalculatorService $gridCalculator,
        private TelegramService $telegram,
    ) {}

    /**
     * Attempt re-entry for a stopped bot.
     *
     * @return array{success: bool, reason: string}
     */
    public function attemptReentry(Bot $bot, string $trigger = 'automatic'): array
    {
        if ($bot->status !== BotStatus::Stopped) {
            return ['success' => false, 'reason' => 'Bot no está detenido'];
        }

        if ($bot->stop_reason !== 'risk_guard') {
            return ['success' => false, 'reason' => 'Bot no fue detenido por Risk Guard'];
        }

        $checks = [
            fn () => $this->checkAccount($bot),
            fn () => $this->checkPriceInRange($bot),
            fn () => $this->checkLiquidationDistance($bot),
            fn () => $this->checkRecentErrors($bot),
            fn () => $this->checkRecentRebuilds($bot),
        ];

        // Cooldown only applies to automatic triggers, not manual
        if ($trigger === 'automatic') {
            array_unshift($checks, fn () => $this->checkCooldown($bot));
        }

        foreach ($checks as $check) {
            $blockReason = $check();
            if ($blockReason !== null) {
                $this->botRepository->update($bot, ['reentry_last_attempt_at' => now()]);
                $this->logBlocked($bot, $blockReason, $trigger);
                return ['success' => false, 'reason' => $blockReason];
            }
        }

        return $this->executeReentry($bot, $trigger);
    }

    /**
     * Process all automatic re-entry candidates.
     */
    public function processAutomaticReentries(): int
    {
        $candidates = Bot::reentryCandidates()->get();
        $processed = 0;

        foreach ($candidates as $bot) {
            $result = $this->attemptReentry($bot, 'automatic');
            if ($result['success']) {
                $processed++;
            }
        }

        return $processed;
    }

    // ── Condition checks ────────────────────────────────────────────────

    private function checkCooldown(Bot $bot): ?string
    {
        $cooldownMinutes = $bot->reentry_cooldown_minutes ?: 60;
        $lastAttempt = $bot->reentry_last_attempt_at;

        // Only enforce cooldown between automatic attempts
        if (!$lastAttempt) {
            return null;
        }

        $elapsed = $lastAttempt->diffInMinutes(now());
        if ($elapsed < $cooldownMinutes) {
            $remaining = $cooldownMinutes - $elapsed;
            return "Cooldown activo: faltan {$remaining} minutos";
        }

        return null;
    }

    private function checkAccount(Bot $bot): ?string
    {
        $account = $bot->binanceAccount;
        if (!$account instanceof BinanceAccount) {
            return 'Sin cuenta Binance vinculada';
        }
        return null;
    }

    private function checkPriceInRange(Bot $bot): ?string
    {
        $account = $bot->binanceAccount;
        if (!$account) {
            return 'Sin cuenta para verificar precio';
        }

        try {
            $currentPrice = $this->binance->getCurrentPrice($account, $bot->symbol);
        } catch (\Exception) {
            return 'Error obteniendo precio actual';
        }

        if (!$currentPrice) {
            return 'No se pudo obtener precio actual';
        }

        $lower = (float) $bot->price_lower;
        $upper = (float) $bot->price_upper;
        $range = $upper - $lower;
        $margin = $range * 0.15; // 15% buffer around grid range

        if ($currentPrice < ($lower - $margin) || $currentPrice > ($upper + $margin)) {
            return "Precio \${$this->fmt($currentPrice)} fuera de zona aceptable (" .
                "\${$this->fmt($lower - $margin)} – \${$this->fmt($upper + $margin)})";
        }

        return null;
    }

    private function checkLiquidationDistance(Bot $bot): ?string
    {
        $account = $bot->binanceAccount;
        if (!$account) {
            return null; // skip if no account
        }

        try {
            $positions = $this->binance->getPositions($account, $bot->symbol);
        } catch (\Exception) {
            return null; // skip on error, don't block re-entry for API issues
        }

        foreach ($positions as $pos) {
            $markPrice = (float) ($pos['markPrice'] ?? 0);
            $liqPrice = (float) ($pos['liquidationPrice'] ?? 0);
            $posAmt = (float) ($pos['positionAmt'] ?? 0);

            if ($markPrice <= 0 || $liqPrice <= 0 || $posAmt == 0) {
                continue;
            }

            $distancePct = abs(($markPrice - $liqPrice) / $markPrice) * 100;
            if ($distancePct < 20.0) {
                return "Distancia a liquidación demasiado baja: {$this->fmt($distancePct)}% (mínimo 20%)";
            }
        }

        return null;
    }

    private function checkRecentErrors(Bot $bot): ?string
    {
        $count = (int) Cache::get("risk_guard.errors.{$bot->id}", 0);
        if ($count >= 3) {
            return "Errores recientes del exchange: {$count} (se requieren < 3)";
        }
        return null;
    }

    private function checkRecentRebuilds(Bot $bot): ?string
    {
        $count = (int) Cache::get("risk_guard.rebuilds.{$bot->id}", 0);
        if ($count >= 3) {
            return "Demasiados rebuilds recientes: {$count} (se requieren < 3)";
        }
        return null;
    }

    // ── Execution ───────────────────────────────────────────────────────

    /**
     * @return array{success: bool, reason: string}
     */
    private function executeReentry(Bot $bot, string $trigger): array
    {
        $account = $bot->binanceAccount;

        try {
            $currentPrice = $this->binance->getCurrentPrice($account, $bot->symbol);
        } catch (\Exception $e) {
            return ['success' => false, 'reason' => 'Error obteniendo precio: ' . $e->getMessage()];
        }

        if (!$currentPrice) {
            return ['success' => false, 'reason' => 'No se pudo obtener precio actual'];
        }

        $beforeState = BotActivityLogger::captureState($bot);
        $oldLower = (float) $bot->price_lower;
        $oldUpper = (float) $bot->price_upper;
        $range = $oldUpper - $oldLower;

        // Recenter grid around current price (same logic as autoRebuildIfEmpty)
        $side = $bot->side;
        if ($side === \App\Enums\BotSide::Long) {
            $newLower = $currentPrice - 0.4 * $range;
            $newUpper = $currentPrice + 0.6 * $range;
        } elseif ($side === \App\Enums\BotSide::Short) {
            $newLower = $currentPrice - 0.6 * $range;
            $newUpper = $currentPrice + 0.4 * $range;
        } else {
            $newLower = $currentPrice - 0.5 * $range;
            $newUpper = $currentPrice + 0.5 * $range;
        }

        $newLower = max($newLower, 0.01);

        Log::info('ReentryService: executing re-entry', [
            'bot_id' => $bot->id,
            'trigger' => $trigger,
            'current_price' => $currentPrice,
            'new_range' => "{$newLower}-{$newUpper}",
        ]);

        $this->botRepository->update($bot, [
            'price_lower' => $newLower,
            'price_upper' => $newUpper,
            'status' => BotStatus::Pending,
            'stop_reason' => null,
            'risk_guard_level' => null,
            'risk_guard_reason' => null,
            'risk_guard_triggered_at' => null,
            'reentry_last_block_reason' => null,
            'last_error_message' => null,
        ]);

        InitializeBotJob::dispatch($bot);

        $reason = "Grid recentrado a \${$this->fmt($newLower)} – \${$this->fmt($newUpper)} " .
            "(precio: \${$this->fmt($currentPrice)}, trigger: {$trigger})";

        BotActivityLogger::logSystemAction($bot, 'reentry_success', [
            'trigger' => $trigger,
            'current_price' => $currentPrice,
            'old_range' => "{$oldLower}-{$oldUpper}",
            'new_range' => "{$newLower}-{$newUpper}",
        ], $beforeState, BotActivityLogger::captureState($bot));

        $this->notifyTelegram($bot, true, $reason);

        return ['success' => true, 'reason' => $reason];
    }

    private function logBlocked(Bot $bot, string $reason, string $trigger): void
    {
        $this->botRepository->update($bot, [
            'reentry_last_block_reason' => $reason,
        ]);

        BotActivityLogger::logSystemAction($bot, 'reentry_blocked', [
            'trigger' => $trigger,
            'reason' => $reason,
        ], null, null, BotActivityLogger::RESULT_BLOCKED);

        Log::info('ReentryService: re-entry blocked', [
            'bot_id' => $bot->id,
            'trigger' => $trigger,
            'reason' => $reason,
        ]);

        $this->notifyTelegram($bot, false, $reason);
    }

    private function notifyTelegram(Bot $bot, bool $success, string $reason): void
    {
        if (!$bot->ai_notify_telegram) {
            return;
        }

        $events = $bot->ai_notify_events ?? [];
        $matchEvents = $success
            ? ['reentry_success', 'risk_guard_triggered', 'bot_stopped']
            : ['reentry_blocked', 'risk_guard_triggered'];

        if (empty(array_intersect($matchEvents, $events))) {
            return;
        }

        $user = $bot->user;
        if (!$user || empty($user->telegram_chat_id)) {
            return;
        }

        try {
            $message = $this->telegram->formatReentryNotification(
                $bot->name,
                $bot->symbol,
                $success,
                $reason,
            );
            $this->telegram->sendMessage($user->telegram_chat_id, $message);
        } catch (\Exception $e) {
            Log::warning('ReentryService: telegram failed', [
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
