<?php

namespace App\Repositories;

use App\Models\BinanceAccount;
use Illuminate\Database\Eloquent\Collection;

class BinanceAccountRepository
{
    public function getByUser(int $userId): Collection
    {
        return BinanceAccount::where('user_id', $userId)
            ->latest()
            ->get();
    }

    public function findActive(int $userId): ?BinanceAccount
    {
        return BinanceAccount::where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    public function find(int $id): ?BinanceAccount
    {
        return BinanceAccount::find($id);
    }

    public function create(array $data): BinanceAccount
    {
        return BinanceAccount::create($data);
    }

    public function update(BinanceAccount $account, array $data): BinanceAccount
    {
        $account->update($data);
        return $account->fresh();
    }

    public function delete(BinanceAccount $account): bool
    {
        return $account->delete();
    }
}
