<?php

namespace App\Models;

use App\Enums\OrderSide;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;
    protected $fillable = [
        'bot_id',
        'side',
        'status',
        'price',
        'quantity',
        'grid_level',
        'pnl',
        'binance_order_id',
        'filled_at',
    ];

    protected $casts = [
        'side' => OrderSide::class,
        'status' => OrderStatus::class,
        'price' => 'decimal:8',
        'quantity' => 'decimal:8',
        'grid_level' => 'integer',
        'pnl' => 'decimal:4',
        'filled_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', OrderStatus::Open);
    }

    public function scopeBuys($query)
    {
        return $query->where('side', OrderSide::Buy);
    }

    public function scopeSells($query)
    {
        return $query->where('side', OrderSide::Sell);
    }
}
