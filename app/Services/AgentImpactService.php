<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotActionLog;
use App\Models\BotPnlSnapshot;
use Illuminate\Support\Carbon;

class AgentImpactService
{
    /**
     * How long (minutes) after an agent action we consider the PNL
     * interval "agent-influenced". 60 min = reasonable decay window.
     */
    private const INFLUENCE_WINDOW_MIN = 60;

    /**
     * Compare bot performance during agent-influenced vs system-only periods.
     *
     * Method: walk the PNL snapshot series chronologically. For each consecutive
     * pair (Δpnl over ~5 min), classify the interval as "agent-influenced" when
     * any agent action occurred within the preceding INFLUENCE_WINDOW_MIN, or
     * "system-only" otherwise. Aggregate PNL, hours, and PNL/hour for each bucket.
     *
     * @return array{
     *   agent: array{pnl: float, hours: float, pnl_per_hour: float, intervals: int},
     *   system: array{pnl: float, hours: float, pnl_per_hour: float, intervals: int},
     *   agent_actions: array{total: int, by_type: array<string, int>},
     *   agent_actions_success: int,
     *   agent_actions_failed: int,
     *   runtime_hours: float,
     *   agent_coverage_pct: float,
     *   snapshots_used: int,
     *   data_since: string|null,
     *   influence_window_min: int,
     * }
     */
    public function compare(Bot $bot): array
    {
        $snapshots = BotPnlSnapshot::where('bot_id', $bot->id)
            ->orderBy('snapshot_at')
            ->get(['total_pnl', 'grid_profit', 'snapshot_at']);

        $agentActions = BotActionLog::where('bot_id', $bot->id)
            ->where('source', 'agent')
            ->orderBy('created_at')
            ->get(['action', 'result', 'created_at']);

        $empty = [
            'agent' => ['pnl' => 0, 'hours' => 0, 'pnl_per_hour' => 0, 'intervals' => 0],
            'system' => ['pnl' => 0, 'hours' => 0, 'pnl_per_hour' => 0, 'intervals' => 0],
            'agent_actions' => ['total' => 0, 'by_type' => []],
            'agent_actions_success' => 0,
            'agent_actions_failed' => 0,
            'runtime_hours' => 0,
            'agent_coverage_pct' => 0,
            'snapshots_used' => 0,
            'data_since' => null,
            'influence_window_min' => self::INFLUENCE_WINDOW_MIN,
        ];

        if ($snapshots->count() < 2) {
            return $empty;
        }

        // Pre-compute agent action timestamps for fast window lookup
        $actionTimestamps = $agentActions->pluck('created_at')->map(
            fn (Carbon $dt) => $dt->timestamp
        )->toArray();

        $agentPnl = 0.0;
        $agentHours = 0.0;
        $agentIntervals = 0;
        $systemPnl = 0.0;
        $systemHours = 0.0;
        $systemIntervals = 0;

        $windowSeconds = self::INFLUENCE_WINDOW_MIN * 60;

        $prev = $snapshots->first();
        foreach ($snapshots->slice(1) as $snap) {
            $deltaPnl = (float) $snap->total_pnl - (float) $prev->total_pnl;
            $deltaSeconds = $prev->snapshot_at->diffInSeconds($snap->snapshot_at);
            $deltaHours = $deltaSeconds / 3600;

            // Skip anomalous intervals (> 1 hour gap = scheduler was down)
            if ($deltaSeconds > 3600 || $deltaSeconds <= 0) {
                $prev = $snap;
                continue;
            }

            $midpoint = $snap->snapshot_at->timestamp;
            $influenced = $this->isAgentInfluenced($actionTimestamps, $midpoint, $windowSeconds);

            if ($influenced) {
                $agentPnl += $deltaPnl;
                $agentHours += $deltaHours;
                $agentIntervals++;
            } else {
                $systemPnl += $deltaPnl;
                $systemHours += $deltaHours;
                $systemIntervals++;
            }

            $prev = $snap;
        }

        $totalHours = $agentHours + $systemHours;

        // Agent actions breakdown
        $byType = [];
        $successCount = 0;
        $failedCount = 0;
        foreach ($agentActions as $a) {
            $byType[$a->action] = ($byType[$a->action] ?? 0) + 1;
            if ($a->result === 'success') {
                $successCount++;
            } else {
                $failedCount++;
            }
        }
        arsort($byType);

        return [
            'agent' => [
                'pnl' => round($agentPnl, 4),
                'hours' => round($agentHours, 2),
                'pnl_per_hour' => $agentHours > 0.1 ? round($agentPnl / $agentHours, 4) : 0,
                'intervals' => $agentIntervals,
            ],
            'system' => [
                'pnl' => round($systemPnl, 4),
                'hours' => round($systemHours, 2),
                'pnl_per_hour' => $systemHours > 0.1 ? round($systemPnl / $systemHours, 4) : 0,
                'intervals' => $systemIntervals,
            ],
            'agent_actions' => [
                'total' => $agentActions->count(),
                'by_type' => $byType,
            ],
            'agent_actions_success' => $successCount,
            'agent_actions_failed' => $failedCount,
            'runtime_hours' => round($totalHours, 2),
            'agent_coverage_pct' => $totalHours > 0 ? round(($agentHours / $totalHours) * 100, 1) : 0,
            'snapshots_used' => $snapshots->count(),
            'data_since' => $snapshots->first()->snapshot_at->toIso8601String(),
            'influence_window_min' => self::INFLUENCE_WINDOW_MIN,
        ];
    }

    /**
     * Binary search: was any agent action within [midpoint - window, midpoint]?
     *
     * @param int[] $timestamps sorted ascending
     */
    private function isAgentInfluenced(array $timestamps, int $midpoint, int $windowSeconds): bool
    {
        if (empty($timestamps)) {
            return false;
        }

        $windowStart = $midpoint - $windowSeconds;

        // Binary search for first timestamp >= windowStart
        $lo = 0;
        $hi = count($timestamps) - 1;
        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($timestamps[$mid] < $windowStart) {
                $lo = $mid + 1;
            } else {
                $hi = $mid - 1;
            }
        }

        // $lo = index of first timestamp >= windowStart
        return $lo < count($timestamps) && $timestamps[$lo] <= $midpoint;
    }
}
