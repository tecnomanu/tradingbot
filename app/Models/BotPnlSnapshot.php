<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotPnlSnapshot extends Model
{
    use HasFactory;
    protected $fillable = [
        'bot_id',
        'total_pnl',
        'grid_profit',
        'total_fees',
        'trend_pnl',
        'unrealized_pnl',
        'snapshot_at',
    ];

    protected $casts = [
        'total_pnl' => 'decimal:4',
        'grid_profit' => 'decimal:4',
        'total_fees' => 'decimal:4',
        'trend_pnl' => 'decimal:4',
        'unrealized_pnl' => 'decimal:4',
        'snapshot_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
