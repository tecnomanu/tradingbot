<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderApiController extends Controller
{
    use ApiResponse;

    /**
     * List orders for a specific bot with optional status filter.
     *
     * Query params:
     *   status  = open|filled|cancelled  (default: all)
     *   side    = buy|sell               (default: all)
     *   limit   = int                    (default: 100, max 500)
     */
    public function byBot(Request $request, Bot $bot): JsonResponse
    {
        abort_if($bot->user_id !== $request->user()->id, 403, 'Forbidden');
        $query = $bot->orders();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($side = $request->query('side')) {
            $query->where('side', $side);
        }

        $limit  = min((int) ($request->query('limit', 100)), 500);
        $orders = $query->orderBy('price')->limit($limit)->get();

        return $this->successResponse([
            'bot_id'  => $bot->id,
            'filters' => ['status' => $status, 'side' => $side, 'limit' => $limit],
            'total'   => $orders->count(),
            'orders'  => $this->formatOrders($orders),
        ]);
    }

    /**
     * Global order overview for the authenticated user's bots.
     *
     * Query params:
     *   status = open|filled|cancelled
     *   limit  = int (default 200, max 1000)
     */
    public function global(Request $request): JsonResponse
    {
        $userBotIds = Bot::where('user_id', $request->user()->id)->pluck('id');

        $query = Order::with('bot:id,name,symbol,status')
            ->whereIn('bot_id', $userBotIds);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $limit  = min((int) ($request->query('limit', 200)), 1000);
        $orders = $query->orderByDesc('updated_at')->limit($limit)->get();

        // Aggregate stats (scoped to user's bots)
        $allOrders = Order::whereIn('bot_id', $userBotIds)->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "open"      THEN 1 ELSE 0 END) as open_count,
            SUM(CASE WHEN status = "filled"    THEN 1 ELSE 0 END) as filled_count,
            SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN status = "filled"    THEN pnl ELSE 0 END) as total_pnl
        ')->first();

        return $this->successResponse([
            'aggregate' => [
                'total'          => (int) $allOrders->total,
                'open'           => (int) $allOrders->open_count,
                'filled'         => (int) $allOrders->filled_count,
                'cancelled'      => (int) $allOrders->cancelled_count,
                'total_pnl_usdt' => round((float) $allOrders->total_pnl, 4),
            ],
            'filters' => ['status' => $status, 'limit' => $limit],
            'orders'  => $orders->map(fn ($o) => array_merge(
                $this->formatOrder($o),
                ['bot' => ['id' => $o->bot?->id, 'name' => $o->bot?->name, 'symbol' => $o->bot?->symbol]]
            ))->values()->all(),
        ]);
    }

    /**
     * Summary stats of orders for a bot: open, filled 24h, best/worst fills.
     */
    public function stats(Request $request, Bot $bot): JsonResponse
    {
        abort_if($bot->user_id !== $request->user()->id, 403, 'Forbidden');
        $open     = $bot->orders()->where('status', 'open')->count();
        $filled   = $bot->orders()->where('status', 'filled')->count();
        $filled24h = $bot->orders()
            ->where('status', 'filled')
            ->where('filled_at', '>=', now()->subDay())
            ->count();

        $buys  = $bot->orders()->where('side', 'buy')->where('status', 'filled')->count();
        $sells = $bot->orders()->where('side', 'sell')->where('status', 'filled')->count();

        $pnl = $bot->orders()
            ->where('status', 'filled')
            ->selectRaw('SUM(pnl) as total, MAX(pnl) as best, MIN(pnl) as worst')
            ->first();

        $lastFill = $bot->orders()
            ->where('status', 'filled')
            ->latest('filled_at')
            ->first(['price', 'side', 'filled_at']);

        return $this->successResponse([
            'bot_id'     => $bot->id,
            'open'       => $open,
            'filled'     => $filled,
            'filled_24h' => $filled24h,
            'rounds_24h' => (int) floor($filled24h / 2),
            'buys'       => $buys,
            'sells'      => $sells,
            'pnl'        => [
                'total'  => round((float) $pnl?->total, 4),
                'best'   => round((float) $pnl?->best, 4),
                'worst'  => round((float) $pnl?->worst, 4),
            ],
            'last_fill'  => $lastFill ? [
                'price'     => (float) $lastFill->price,
                'side'      => $lastFill->side->value,
                'filled_at' => $lastFill->filled_at?->toIso8601String(),
            ] : null,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal formatters
    // -------------------------------------------------------------------------

    private function formatOrders(\Illuminate\Support\Collection $orders): array
    {
        return $orders->map(fn ($o) => $this->formatOrder($o))->values()->all();
    }

    private function formatOrder(Order $o): array
    {
        return [
            'id'               => $o->id,
            'side'             => $o->side->value,
            'status'           => $o->status->value,
            'price'            => (float) $o->price,
            'quantity'         => (float) $o->quantity,
            'grid_level'       => $o->grid_level,
            'pnl'              => (float) $o->pnl,
            'binance_order_id' => $o->binance_order_id,
            'filled_at'        => $o->filled_at?->toIso8601String(),
            'created_at'       => $o->created_at?->toIso8601String(),
        ];
    }
}
