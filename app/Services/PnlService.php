<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotPnlSnapshot;
use App\Repositories\BotRepository;
use Illuminate\Support\Carbon;

class PnlService
{
    public function __construct(
        private BotRepository $botRepository,
    ) {}

    /**
     * Calculate and store a PNL snapshot for a bot.
     */
    public function takeSnapshot(Bot $bot): BotPnlSnapshot
    {
        return BotPnlSnapshot::create([
            'bot_id' => $bot->id,
            'total_pnl' => $bot->total_pnl,
            'grid_profit' => $bot->grid_profit,
            'total_fees' => $bot->total_fees ?? 0,
            'trend_pnl' => $bot->trend_pnl,
            'unrealized_pnl' => 0,
            'snapshot_at' => now(),
        ]);
    }

    /**
     * Get historical PNL data for charting.
     */
    public function getHistoricalPnl(int $botId, int $hours = 48): array
    {
        $since = Carbon::now()->subHours($hours);

        return BotPnlSnapshot::where('bot_id', $botId)
            ->where('snapshot_at', '>=', $since)
            ->orderBy('snapshot_at')
            ->get()
            ->map(fn($s) => [
                'time' => $s->snapshot_at->format('m/d H:i'),
                'total_pnl' => (float) $s->total_pnl,
                'grid_profit' => (float) $s->grid_profit,
                'trend_pnl' => (float) $s->trend_pnl,
            ])
            ->toArray();
    }

    /**
     * Calculate drawdown metrics from the full snapshot history.
     *
     * Algorithm: walk the equity curve (total_pnl series) chronologically,
     * tracking the running peak. The drawdown at each point is peak − current.
     * We record the maximum drawdown (absolute and %) and its duration.
     *
     * @return array{peak_pnl: float, current_pnl: float, max_drawdown: float, max_drawdown_pct: float, drawdown_duration_minutes: int|null, snapshots_used: int, data_since: string|null}
     */
    public function calculateDrawdown(Bot $bot): array
    {
        $snapshots = BotPnlSnapshot::where('bot_id', $bot->id)
            ->orderBy('snapshot_at')
            ->get(['total_pnl', 'snapshot_at']);

        $investment = (float) $bot->real_investment;

        if ($snapshots->isEmpty()) {
            return [
                'peak_pnl' => 0,
                'current_pnl' => (float) $bot->total_pnl,
                'max_drawdown' => 0,
                'max_drawdown_pct' => 0,
                'drawdown_duration_minutes' => null,
                'snapshots_used' => 0,
                'data_since' => null,
            ];
        }

        $peak = PHP_FLOAT_MIN;
        $maxDd = 0;
        $maxDdPct = 0;

        // Duration tracking: when drawdown starts → when it recovers or ends
        $ddStartTime = null;
        $longestDdMinutes = 0;

        foreach ($snapshots as $snap) {
            $equity = $investment + (float) $snap->total_pnl;
            $pnl = (float) $snap->total_pnl;

            if ($pnl > $peak) {
                // New peak — close any open drawdown period
                if ($ddStartTime !== null) {
                    $minutes = (int) $ddStartTime->diffInMinutes($snap->snapshot_at);
                    $longestDdMinutes = max($longestDdMinutes, $minutes);
                }
                $peak = $pnl;
                $ddStartTime = null;
            } else {
                $dd = $peak - $pnl;
                if ($dd > $maxDd) {
                    $maxDd = $dd;
                    $maxDdPct = ($investment > 0) ? round(($dd / ($investment + $peak)) * 100, 2) : 0;
                }
                if ($ddStartTime === null) {
                    $ddStartTime = $snap->snapshot_at;
                }
            }
        }

        // If still in drawdown at the end, measure open duration
        if ($ddStartTime !== null) {
            $lastSnap = $snapshots->last();
            $minutes = (int) $ddStartTime->diffInMinutes($lastSnap->snapshot_at);
            $longestDdMinutes = max($longestDdMinutes, $minutes);
        }

        $lastPnl = (float) $snapshots->last()->total_pnl;

        return [
            'peak_pnl' => round($peak, 4),
            'current_pnl' => round($lastPnl, 4),
            'max_drawdown' => round($maxDd, 4),
            'max_drawdown_pct' => $maxDdPct,
            'drawdown_duration_minutes' => $longestDdMinutes > 0 ? $longestDdMinutes : null,
            'snapshots_used' => $snapshots->count(),
            'data_since' => $snapshots->first()->snapshot_at->toIso8601String(),
        ];
    }

    /**
     * Get dashboard summary for a user.
     */
    public function getDashboardSummary(int $userId): array
    {
        $stats = $this->botRepository->getUserBotStats($userId);
        $activeBots = $this->botRepository->getActiveByUser($userId);

        // Get combined PNL chart from all active bots
        $pnlChart = [];
        if ($activeBots->isNotEmpty()) {
            $since = Carbon::now()->subHours(48);
            $pnlChart = BotPnlSnapshot::whereIn('bot_id', $activeBots->pluck('id'))
                ->where('snapshot_at', '>=', $since)
                ->orderBy('snapshot_at')
                ->get()
                ->groupBy(fn($s) => $s->snapshot_at->format('m/d H:i'))
                ->map(fn($group) => [
                    'time' => $group->first()->snapshot_at->format('m/d H:i'),
                    'total_pnl' => $group->sum('total_pnl'),
                    'grid_profit' => $group->sum('grid_profit'),
                ])
                ->values()
                ->toArray();
        }

        return [
            'stats' => $stats,
            'active_bots' => $activeBots,
            'pnl_chart' => $pnlChart,
        ];
    }
}
