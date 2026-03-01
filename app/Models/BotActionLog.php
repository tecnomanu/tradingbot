<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotActionLog extends Model
{
    protected $fillable = [
        'bot_id', 'conversation_id', 'action', 'source',
        'details', 'before_state', 'after_state',
    ];

    protected $casts = [
        'details' => 'array',
        'before_state' => 'array',
        'after_state' => 'array',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
