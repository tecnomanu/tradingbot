<?php

namespace App\Models;

use App\Enums\BotSide;
use App\Enums\BotStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'binance_account_id',
        'name',
        'symbol',
        'side',
        'status',
        'last_error_message',
        'price_lower',
        'price_upper',
        'grid_count',
        'grid_mode',
        'investment',
        'leverage',
        'margin_type',
        'slippage',
        'stop_loss_price',
        'take_profit_price',
        'ai_system_prompt',
        'ai_user_prompt',
        'ai_consultation_interval',
        'ai_notify_telegram',
        'ai_notify_events',
        'risk_config',
        'risk_guard_reason',
        'risk_guard_triggered_at',
        'risk_guard_level',
        'stop_reason',
        'reentry_enabled',
        'reentry_cooldown_minutes',
        'reentry_last_attempt_at',
        'reentry_last_block_reason',
        'real_investment',
        'additional_margin',
        'est_liquidation_price',
        'profit_per_grid',
        'commission_per_grid',
        'total_pnl',
        'grid_profit',
        'total_fees',
        'trend_pnl',
        'total_rounds',
        'rounds_24h',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'side' => BotSide::class,
        'status' => BotStatus::class,
        'price_lower' => 'decimal:8',
        'price_upper' => 'decimal:8',
        'grid_count' => 'integer',
        'investment' => 'decimal:4',
        'leverage' => 'integer',
        'slippage' => 'decimal:2',
        'stop_loss_price' => 'decimal:8',
        'take_profit_price' => 'decimal:8',
        'real_investment' => 'decimal:4',
        'additional_margin' => 'decimal:4',
        'est_liquidation_price' => 'decimal:8',
        'profit_per_grid' => 'decimal:4',
        'commission_per_grid' => 'decimal:4',
        'total_pnl' => 'decimal:4',
        'grid_profit' => 'decimal:4',
        'total_fees' => 'decimal:4',
        'trend_pnl' => 'decimal:4',
        'ai_consultation_interval' => 'integer',
        'ai_notify_telegram' => 'boolean',
        'ai_notify_events' => 'array',
        'risk_config' => 'array',
        'risk_guard_triggered_at' => 'datetime',
        'reentry_enabled' => 'boolean',
        'reentry_cooldown_minutes' => 'integer',
        'reentry_last_attempt_at' => 'datetime',
        'total_rounds' => 'integer',
        'rounds_24h' => 'integer',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function binanceAccount(): BelongsTo
    {
        return $this->belongsTo(BinanceAccount::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function pnlSnapshots(): HasMany
    {
        return $this->hasMany(BotPnlSnapshot::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(BotActionLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', BotStatus::Active);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeReentryCandidates($query)
    {
        return $query->where('status', BotStatus::Stopped)
            ->where('stop_reason', 'risk_guard')
            ->where('reentry_enabled', true);
    }

    /**
     * Get the PNL percentage based on real investment.
     */
    public function getPnlPercentageAttribute(): float
    {
        if (!$this->real_investment || $this->real_investment == 0) {
            return 0;
        }
        return round(($this->total_pnl / $this->real_investment) * 100, 2);
    }

    /**
     * Get the price range formatted as string.
     */
    public function getPriceRangeAttribute(): string
    {
        return number_format($this->price_lower, 2) . ' ~ ' . number_format($this->price_upper, 2);
    }
}
