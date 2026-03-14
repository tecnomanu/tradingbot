<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\BotActionLog;
use App\Models\User;

class BotActivityLogger
{
    /**
     * Log a bot activity from any source.
     *
     * @param  string  $source  user|api|agent|system
     */
    public static function log(
        Bot $bot,
        string $action,
        string $source,
        ?User $user = null,
        array $details = [],
        ?array $beforeState = null,
        ?array $afterState = null,
        ?int $conversationId = null,
    ): BotActionLog {
        return BotActionLog::create([
            'bot_id' => $bot->id,
            'user_id' => $user?->id,
            'conversation_id' => $conversationId,
            'action' => $action,
            'source' => $source,
            'details' => $details ?: null,
            'before_state' => $beforeState,
            'after_state' => $afterState,
        ]);
    }

    public static function logUserAction(Bot $bot, string $action, User $user, array $details = [], ?array $beforeState = null, ?array $afterState = null): BotActionLog
    {
        return self::log($bot, $action, 'user', $user, $details, $beforeState, $afterState);
    }

    public static function logApiAction(Bot $bot, string $action, User $user, array $details = [], ?array $beforeState = null, ?array $afterState = null): BotActionLog
    {
        return self::log($bot, $action, 'api', $user, $details, $beforeState, $afterState);
    }

    public static function logAgentAction(Bot $bot, string $action, array $details = [], ?int $conversationId = null): BotActionLog
    {
        return self::log($bot, $action, 'agent', null, $details, null, null, $conversationId);
    }

    public static function logSystemAction(Bot $bot, string $action, array $details = []): BotActionLog
    {
        return self::log($bot, $action, 'system', null, $details);
    }

    public static function captureState(Bot $bot): array
    {
        return [
            'status' => $bot->status->value,
            'price_lower' => (float) $bot->price_lower,
            'price_upper' => (float) $bot->price_upper,
            'grid_count' => $bot->grid_count,
            'investment' => (float) $bot->investment,
            'leverage' => $bot->leverage,
            'stop_loss_price' => $bot->stop_loss_price ? (float) $bot->stop_loss_price : null,
            'take_profit_price' => $bot->take_profit_price ? (float) $bot->take_profit_price : null,
            'grid_mode' => $bot->grid_mode,
        ];
    }
}
