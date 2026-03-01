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
        return [
            'total_orders' => Order::where('bot_id', $botId)->count(),
            'open_orders' => Order::where('bot_id', $botId)->open()->count(),
            'filled_orders' => Order::where('bot_id', $botId)->where('status', OrderStatus::Filled)->count(),
            'total_pnl' => Order::where('bot_id', $botId)->where('status', OrderStatus::Filled)->sum('pnl'),
        ];
    }

    public function deleteByBot(int $botId): int
    {
        return Order::where('bot_id', $botId)->delete();
    }
}
