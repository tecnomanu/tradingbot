<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function activeBots(Request $request): Response
    {
        $bots = Bot::where('user_id', $request->user()->id)
            ->whereIn('status', ['active', 'pending'])
            ->with('binanceAccount:id,label,is_testnet')
            ->withCount([
                'orders as open_orders_count' => fn ($q) => $q->where('status', 'open'),
                'orders as filled_orders_count' => fn ($q) => $q->where('status', 'filled'),
            ])
            ->orderByDesc('started_at')
            ->get()
            ->map(function ($bot) {
                $bot->filled_24h = $bot->orders()
                    ->where('status', 'filled')
                    ->where('filled_at', '>=', now()->subDay())
                    ->count();
                $bot->rounds_24h = (int) floor($bot->filled_24h / 2);
                return $bot;
            });

        return Inertia::render('Orders/ActiveBots', [
            'bots' => $bots,
        ]);
    }

    public function botHistory(Request $request): Response
    {
        $bots = Bot::where('user_id', $request->user()->id)
            ->whereIn('status', ['stopped', 'error', 'completed'])
            ->with('binanceAccount:id,label,is_testnet')
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Orders/BotHistory', [
            'bots' => $bots,
        ]);
    }

    public function openOrders(Request $request): Response
    {
        $orders = Order::whereHas('bot', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', 'open')
            ->with('bot:id,name,symbol,side')
            ->orderByDesc('created_at')
            ->paginate(50);

        return Inertia::render('Orders/OpenOrders', [
            'orders' => $orders,
        ]);
    }

    public function orderHistory(Request $request): Response
    {
        $query = Order::whereHas('bot', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('bot:id,name,symbol,side');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['filled', 'cancelled', 'open']);
        }

        if ($request->filled('side') && $request->side !== 'all') {
            $query->where('side', $request->side);
        }

        if ($request->filled('symbol') && $request->symbol !== 'all') {
            $query->whereHas('bot', fn ($q) => $q->where('symbol', $request->symbol));
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $allowedSorts = ['created_at', 'filled_at', 'price', 'quantity', 'pnl', 'status', 'side'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }

        $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');

        $orders = $query->paginate(50)->withQueryString();

        $symbols = Order::whereHas('bot', fn ($q) => $q->where('user_id', $request->user()->id))
            ->join('bots', 'orders.bot_id', '=', 'bots.id')
            ->distinct()
            ->pluck('bots.symbol')
            ->values();

        return Inertia::render('Orders/OrderHistory', [
            'orders' => $orders,
            'filters' => [
                'status' => $request->get('status', 'all'),
                'side' => $request->get('side', 'all'),
                'symbol' => $request->get('symbol', 'all'),
                'sort' => $sortField,
                'dir' => $sortDir,
            ],
            'availableSymbols' => $symbols,
        ]);
    }

    public function positions(Request $request): Response
    {
        $bots = Bot::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->with('binanceAccount:id,label')
            ->withCount([
                'orders as open_orders_count' => fn ($q) => $q->where('status', 'open'),
                'orders as filled_orders_count' => fn ($q) => $q->where('status', 'filled'),
            ])
            ->get()
            ->map(function ($bot) {
                $filled24h = $bot->orders()
                    ->where('status', 'filled')
                    ->where('filled_at', '>=', now()->subDay())
                    ->count();

                return [
                    'id' => $bot->id,
                    'symbol' => $bot->symbol,
                    'side' => $bot->side,
                    'leverage' => $bot->leverage,
                    'investment' => $bot->real_investment,
                    'pnl' => $bot->total_pnl,
                    'grid_profit' => $bot->grid_profit,
                    'trend_pnl' => $bot->trend_pnl,
                    'liquidation_price' => $bot->est_liquidation_price,
                    'price_lower' => $bot->price_lower,
                    'price_upper' => $bot->price_upper,
                    'grid_count' => $bot->grid_count,
                    'total_rounds' => $bot->total_rounds,
                    'rounds_24h' => (int) floor($filled24h / 2),
                    'started_at' => $bot->started_at,
                    'open_orders_count' => $bot->open_orders_count,
                    'filled_orders_count' => $bot->filled_orders_count,
                ];
            });

        return Inertia::render('Orders/Positions', [
            'positions' => $bots,
        ]);
    }
}
