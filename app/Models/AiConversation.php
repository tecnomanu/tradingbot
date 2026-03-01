<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    protected $fillable = [
        'bot_id', 'status', 'trigger', 'model', 'summary',
        'total_tokens', 'total_tool_calls', 'total_messages',
        'duration_ms', 'actions_taken', 'started_at', 'ended_at',
    ];

    protected $casts = [
        'actions_taken' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiConversationMessage::class, 'conversation_id');
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(BotActionLog::class, 'conversation_id');
    }
}
