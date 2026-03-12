<?php

namespace App\Repositories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrderRepository
{
    public function getByBot(int $botId, int $perPage = 50): LengthAwarePaginator
    {
        return Order::where('bot_id', $botId)
            ->latest()
            ->paginate($perPage);
    }

    public function getOpenByBot(int $botId): Collection
    {
        return Order::where('bot_id', $botId)
            ->open()
            ->orderBy('price')
            ->get();
    }

    public function getFilledByBot(int $botId): Collection
    {
        return Order::where('bot_id', $botId)
            ->where('status', OrderStatus::Filled)
            ->latest('filled_at')
            ->get();
    }

    public function createMany(array $orders): bool
    {
        return Order::insert($orders);
    }

    public function updateStatus(Order $order, OrderStatus $status, ?float $pnl = null): Order
    {
        $data = ['status' => $status];
        if ($status === OrderStatus::Filled) {
            $data['filled_at'] = now();
        }
        if ($pnl !== null) {
            $data['pnl'] = $pnl;
        }
        $order->update($data);
        return $order;
    }

    public function getBotOrderStats(int $botId): array
    {
        $stats = Order::where('bot_id', $botId)
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_orders,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as filled_orders,
                COALESCE(SUM(CASE WHEN status = ? THEN pnl ELSE 0 END), 0) as total_pnl
            ', [OrderStatus::Open->value, OrderStatus::Filled->value, OrderStatus::Filled->value])
            ->first();

        return [
            'total_orders' => (int) ($stats->total_orders ?? 0),
            'open_orders' => (int) ($stats->open_orders ?? 0),
            'filled_orders' => (int) ($stats->filled_orders ?? 0),
            'total_pnl' => (float) ($stats->total_pnl ?? 0),
        ];
    }

    public function deleteByBot(int $botId): int
    {
        return Order::where('bot_id', $botId)->delete();
    }
}
