<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentLog extends Model
{
    protected $fillable = [
        'bot_id',
        'symbol',
        'action',
        'signal',
        'confidence',
        'market_data',
        'reasoning',
        'suggestion',
        'applied',
        'model',
        'tokens_used',
        'latency_ms',
    ];

    protected $casts = [
        'market_data' => 'array',
        'suggestion' => 'array',
        'applied' => 'boolean',
        'confidence' => 'float',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
