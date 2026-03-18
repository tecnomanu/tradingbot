<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BotSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                    => $this->id,
            'name'                  => $this->name,
            'symbol'                => $this->symbol,
            'side'                  => $this->side->value,
            'status'                => $this->status->value,
            'account'               => $this->whenLoaded('binanceAccount', fn() => $this->binanceAccount?->label),
            'is_testnet'            => (bool) $this->whenLoaded('binanceAccount', fn() => $this->binanceAccount?->is_testnet),
            'price_lower'           => (float) $this->price_lower,
            'price_upper'           => (float) $this->price_upper,
            'grid_count'            => $this->grid_count,
            'investment'            => (float) $this->investment,
            'real_investment'       => (float) $this->real_investment,
            'leverage'              => $this->leverage,
            'stop_loss_price'       => $this->stop_loss_price ? (float) $this->stop_loss_price : null,
            'take_profit_price'     => $this->take_profit_price ? (float) $this->take_profit_price : null,
            'total_pnl'             => (float) $this->total_pnl,
            'grid_profit'           => (float) $this->grid_profit,
            'trend_pnl'             => (float) $this->trend_pnl,
            'pnl_pct'               => $this->pnl_percentage,
            'total_rounds'          => (int) $this->total_rounds,
            'rounds_24h'            => (int) $this->rounds_24h,
            'profit_per_grid'       => (float) $this->profit_per_grid,
            'est_liquidation_price' => $this->est_liquidation_price ? (float) $this->est_liquidation_price : null,
            'open_orders_count'     => (int) ($this->open_orders_count ?? $this->orders?->where('status', 'open')->count() ?? 0),
            'filled_orders_count'   => (int) ($this->filled_orders_count ?? $this->orders?->where('status', 'filled')->count() ?? 0),
            'started_at'            => $this->started_at?->toIso8601String(),
            'stopped_at'            => $this->stopped_at?->toIso8601String(),
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}
