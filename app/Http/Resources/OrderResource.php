<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'side'             => $this->side->value,
            'status'           => $this->status->value,
            'price'            => (float) $this->price,
            'quantity'         => (float) $this->quantity,
            'grid_level'       => $this->grid_level,
            'pnl'              => (float) $this->pnl,
            'binance_order_id' => $this->binance_order_id,
            'filled_at'        => $this->filled_at?->toIso8601String(),
            'created_at'       => $this->created_at?->toIso8601String(),
            'bot'              => $this->whenLoaded('bot', fn() => [
                'id'     => $this->bot->id,
                'name'   => $this->bot->name,
                'symbol' => $this->bot->symbol,
            ]),
        ];
    }
}
