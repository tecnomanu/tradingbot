<?php

namespace App\Http\Controllers;

use App\Models\AiConversation;
use App\Models\BinanceAccount;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Models\Order;
use App\Services\PnlService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private PnlService $pnlService,
    ) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $summary = $this->pnlService->getDashboardSummary($userId);

        $botIds = Bot::where('user_id', $userId)->pluck('id');
        $activeIds = Bot::where('user_id', $userId)->where('status', 'active')->pluck('id');

        $totalOrders = Order::whereIn('bot_id', $botIds)->count();
        $openOrders = Order::whereIn('bot_id', $activeIds)->where('status', 'open')->count();
        $filledOrders = Order::whereIn('bot_id', $botIds)->where('status', 'filled')->count();
        $filled24h = Order::whereIn('bot_id', $activeIds)
            ->where('status', 'filled')
            ->where('filled_at', '>=', now()->subDay())
            ->count();

        $accounts = BinanceAccount::where('user_id', $userId)->get(['id', 'is_active', 'is_testnet']);

        $aiConversations = AiConversation::whereIn('bot_id', $botIds)->count();
        $aiActions = BotActionLog::whereIn('bot_id', $botIds)->count();
        $lastAiConsult = AiConversation::whereIn('bot_id', $botIds)
            ->latest('ended_at')
            ->value('ended_at');

        $recentOrders = Order::whereIn('bot_id', $botIds)
            ->where('status', 'filled')
            ->with('bot:id,symbol')
            ->latest('filled_at')
            ->limit(8)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'symbol' => $o->bot?->symbol,
                'side' => $o->side->value,
                'price' => (float) $o->price,
                'quantity' => (float) $o->quantity,
                'pnl' => (float) ($o->pnl ?? 0),
                'filled_at' => $o->filled_at?->toIso8601String(),
            ]);

        $recentActions = BotActionLog::whereIn('bot_id', $botIds)
            ->with(['bot:id,symbol', 'user:id,name'])
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'symbol' => $a->bot?->symbol,
                'action' => $a->action,
                'source' => $a->source,
                'actor_label' => $a->actor_label,
                'created_at' => $a->created_at->toIso8601String(),
            ]);

        return Inertia::render('Dashboard/Index', [
            'stats' => $summary['stats'],
            'activeBots' => $summary['active_bots'],
            'pnlChart' => $summary['pnl_chart'],
            'extended' => [
                'total_orders' => $totalOrders,
                'open_orders' => $openOrders,
                'filled_orders' => $filledOrders,
                'filled_24h' => $filled24h,
                'rounds_24h' => (int) floor($filled24h / 2),
                'accounts_total' => $accounts->count(),
                'accounts_active' => $accounts->where('is_active', true)->count(),
                'accounts_testnet' => $accounts->where('is_testnet', true)->count(),
                'ai_conversations' => $aiConversations,
                'ai_actions' => $aiActions,
                'last_ai_consult' => $lastAiConsult?->toIso8601String(),
                'total_bots_stopped' => Bot::where('user_id', $userId)->where('status', 'stopped')->count(),
                'total_bots_error' => Bot::where('user_id', $userId)->where('status', 'error')->count(),
                'trend_pnl' => Bot::where('user_id', $userId)->sum('trend_pnl'),
            ],
            'recentOrders' => $recentOrders,
            'recentActions' => $recentActions,
        ]);
    }
}
