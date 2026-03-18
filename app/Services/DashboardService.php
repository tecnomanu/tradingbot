<?php

namespace App\Services;

use App\Models\AiConversation;
use App\Models\BinanceAccount;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Models\Order;

class DashboardService
{
    public function __construct(
        private PnlService $pnlService,
    ) {}

    public function getDashboardData(int $userId): array
    {
        $summary = $this->pnlService->getDashboardSummary($userId);

        $botIds = Bot::where('user_id', $userId)->pluck('id');
        $activeIds = Bot::where('user_id', $userId)->where('status', 'active')->pluck('id');

        $filled24h = Order::whereIn('bot_id', $activeIds)
            ->where('status', 'filled')
            ->where('filled_at', '>=', now()->subDay())
            ->count();

        $accounts = BinanceAccount::where('user_id', $userId)->get(['id', 'is_active', 'is_testnet']);

        return [
            'stats'        => $summary['stats'],
            'activeBots'   => $summary['active_bots'],
            'pnlChart'     => $summary['pnl_chart'],
            'extended'     => $this->buildExtendedStats($userId, $botIds, $activeIds, $filled24h, $accounts),
            'recentOrders' => $this->getRecentOrders($botIds),
            'recentActions' => $this->getRecentActions($botIds),
        ];
    }

    private function buildExtendedStats(int $userId, $botIds, $activeIds, int $filled24h, $accounts): array
    {
        return [
            'total_orders'       => Order::whereIn('bot_id', $botIds)->count(),
            'open_orders'        => Order::whereIn('bot_id', $activeIds)->where('status', 'open')->count(),
            'filled_orders'      => Order::whereIn('bot_id', $botIds)->where('status', 'filled')->count(),
            'filled_24h'         => $filled24h,
            'rounds_24h'         => (int) floor($filled24h / 2),
            'accounts_total'     => $accounts->count(),
            'accounts_active'    => $accounts->where('is_active', true)->count(),
            'accounts_testnet'   => $accounts->where('is_testnet', true)->count(),
            'ai_conversations'   => AiConversation::whereIn('bot_id', $botIds)->count(),
            'ai_actions'         => BotActionLog::whereIn('bot_id', $botIds)->count(),
            'last_ai_consult'    => AiConversation::whereIn('bot_id', $botIds)->latest('ended_at')->value('ended_at')?->toIso8601String(),
            'total_bots_stopped' => Bot::where('user_id', $userId)->where('status', 'stopped')->count(),
            'total_bots_error'   => Bot::where('user_id', $userId)->where('status', 'error')->count(),
            'trend_pnl'          => Bot::where('user_id', $userId)->sum('trend_pnl'),
        ];
    }

    private function getRecentOrders($botIds): array
    {
        return Order::whereIn('bot_id', $botIds)
            ->where('status', 'filled')
            ->with('bot:id,symbol')
            ->latest('filled_at')
            ->limit(8)
            ->get()
            ->map(fn($o) => [
                'id'        => $o->id,
                'symbol'    => $o->bot?->symbol,
                'side'      => $o->side->value,
                'price'     => (float) $o->price,
                'quantity'  => (float) $o->quantity,
                'pnl'       => (float) ($o->pnl ?? 0),
                'filled_at' => $o->filled_at?->toIso8601String(),
            ])
            ->all();
    }

    private function getRecentActions($botIds): array
    {
        return BotActionLog::whereIn('bot_id', $botIds)
            ->with(['bot:id,symbol', 'user:id,name'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($a) => [
                'id'          => $a->id,
                'symbol'      => $a->bot?->symbol,
                'action'      => $a->action,
                'source'      => $a->source,
                'actor_label' => $a->actor_label,
                'created_at'  => $a->created_at->toIso8601String(),
            ])
            ->all();
    }
}
