<?php

namespace App\Models;

use App\Enums\ActionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotActionLog extends Model
{
    protected $fillable = [
        'bot_id', 'user_id', 'conversation_id', 'action', 'source',
        'details', 'before_state', 'after_state', 'result', 'error_message',
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
        $source = ActionSource::tryFrom($this->source);

        if (!$source) {
            return ucfirst($this->source);
        }

        return match ($source) {
            ActionSource::User => $this->user?->name ?? $source->label(),
            ActionSource::Api => $source->label() . ($this->user ? " ({$this->user->name})" : ''),
            default => $source->label(),
        };
    }
}
