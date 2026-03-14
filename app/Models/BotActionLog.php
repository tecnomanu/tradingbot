<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotActionLog extends Model
{
    protected $fillable = [
        'bot_id', 'user_id', 'conversation_id', 'action', 'source',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function getActorLabelAttribute(): string
    {
        return match ($this->source) {
            'user' => $this->user?->name ?? 'Usuario',
            'api' => 'API' . ($this->user ? " ({$this->user->name})" : ''),
            'agent' => 'Agente AI',
            'system' => 'Sistema',
            default => ucfirst($this->source),
        };
    }
}
