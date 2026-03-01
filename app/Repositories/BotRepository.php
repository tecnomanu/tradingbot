<?php

namespace App\Repositories;

use App\Enums\BotStatus;
use App\Models\Bot;
use Illuminate\Database\Eloquent\Collection;

class BotRepository
{
    public function getByUser(int $userId): Collection
    {
        return Bot::forUser($userId)
            ->with('binanceAccount:id,label')
            ->latest()
            ->get();
    }

    public function getActiveByUser(int $userId): Collection
    {
        return Bot::forUser($userId)
            ->active()
            ->with('binanceAccount:id,label')
            ->get();
    }

    public function findWithRelations(int $id): ?Bot
    {
        return Bot::with(['binanceAccount:id,label', 'orders' => function ($q) {
            $q->latest()->limit(100);
        }])->find($id);
    }

    public function create(array $data): Bot
    {
        return Bot::create($data);
    }

    public function update(Bot $bot, array $data): Bot
    {
        $bot->update($data);
        return $bot->fresh();
    }

    public function updateStatus(Bot $bot, BotStatus $status): Bot
    {
        $bot->update(['status' => $status]);
        return $bot;
    }

    public function delete(Bot $bot): bool
    {
        return $bot->delete();
    }

    public function getUserBotStats(int $userId): array
    {
        $bots = Bot::forUser($userId);

        return [
            'total_bots' => $bots->count(),
            'active_bots' => (clone $bots)->active()->count(),
            'total_investment' => (clone $bots)->active()->sum('real_investment'),
            'total_pnl' => (clone $bots)->sum('total_pnl'),
            'total_grid_profit' => (clone $bots)->sum('grid_profit'),
        ];
    }
}
