<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Order;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusApiController extends Controller
{
    use ApiResponse;

    /**
     * Global dashboard snapshot: active bots, open orders, PNL, Horizon status.
     */
    public function overview(Request $request): JsonResponse
    {
        $bots = Bot::where('user_id', $request->user()->id)
            ->with('binanceAccount:id,label,is_testnet')
            ->withCount([
                'orders as open_orders_count'   => fn ($q) => $q->where('status', 'open'),
                'orders as filled_orders_count' => fn ($q) => $q->where('status', 'filled'),
            ])
            ->get();

        $active  = $bots->where('status', 'active');
        $stopped = $bots->where('status', 'stopped');
        $error   = $bots->where('status', 'error');

        $userBotIds = $bots->pluck('id');

        $orderStats = Order::whereIn('bot_id', $userBotIds)->selectRaw('
            SUM(CASE WHEN status = "open"   THEN 1 ELSE 0 END) as open_orders,
            SUM(CASE WHEN status = "filled" THEN 1 ELSE 0 END) as filled_orders,
            SUM(CASE WHEN status = "filled" AND filled_at >= ? THEN 1 ELSE 0 END) as filled_24h,
            SUM(CASE WHEN status = "filled" THEN pnl ELSE 0 END) as total_pnl
        ', [now()->subDay()])->first();

        $horizonStatus = $this->horizonStatus();

        return $this->successResponse([
            'timestamp'  => now()->toIso8601String(),
            'bots'       => [
                'total'   => $bots->count(),
                'active'  => $active->count(),
                'stopped' => $stopped->count(),
                'error'   => $error->count(),
                'active_list' => $active->map(fn ($b) => [
                    'id'          => $b->id,
                    'name'        => $b->name,
                    'symbol'      => $b->symbol,
                    'total_pnl'   => (float) $b->total_pnl,
                    'open_orders' => (int) $b->open_orders_count,
                    'is_testnet'  => (bool) $b->binanceAccount?->is_testnet,
                    'started_at'  => $b->started_at?->toIso8601String(),
                ])->values(),
            ],
            'orders'     => [
                'open'       => (int) $orderStats->open_orders,
                'filled'     => (int) $orderStats->filled_orders,
                'filled_24h' => (int) $orderStats->filled_24h,
                'total_pnl'  => round((float) $orderStats->total_pnl, 4),
            ],
            'horizon'    => $horizonStatus,
        ]);
    }

    /**
     * Check Redis/queue health (best-effort).
     */
    private function horizonStatus(): array
    {
        try {
            $redis = app('redis')->connection();
            $info  = $redis->info('server');
            return [
                'redis_up'      => true,
                'redis_version' => $info['redis_version'] ?? 'unknown',
            ];
        } catch (\Throwable $e) {
            return ['redis_up' => false, 'error' => class_basename($e) . ': ' . $e->getMessage()];
        }
    }
}
