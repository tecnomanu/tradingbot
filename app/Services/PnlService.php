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
            'trend_pnl' => $bot->trend_pnl,
            'unrealized_pnl' => 0, // Calculated from live price
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
