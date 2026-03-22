<?php

namespace App\Services;

use App\Enums\ActionSource;
use App\Models\Bot;
use App\Models\BotActionLog;
use App\Models\User;

class BotActivityLogger
{
    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAILED = 'failed';
    public const RESULT_PARTIAL = 'partial';
    public const RESULT_BLOCKED = 'blocked';

    public static function log(
        Bot $bot,
        string $action,
        ActionSource $source,
        ?User $user = null,
        array $details = [],
        ?array $beforeState = null,
        ?array $afterState = null,
        ?int $conversationId = null,
        string $result = self::RESULT_SUCCESS,
        ?string $errorMessage = null,
    ): BotActionLog {
        return BotActionLog::create([
            'bot_id' => $bot->id,
            'user_id' => $user?->id,
            'conversation_id' => $conversationId,
            'action' => $action,
            'source' => $source->value,
            'details' => $details ?: null,
            'before_state' => $beforeState,
            'after_state' => $afterState,
            'result' => $result,
            'error_message' => $errorMessage,
        ]);
    }

    public static function logUserAction(
        Bot $bot,
        string $action,
        User $user,
        array $details = [],
        ?array $beforeState = null,
        ?array $afterState = null,
        string $result = self::RESULT_SUCCESS,
        ?string $errorMessage = null,
    ): BotActionLog {
        return self::log($bot, $action, ActionSource::User, $user, $details, $beforeState, $afterState, null, $result, $errorMessage);
    }

    public static function logApiAction(
        Bot $bot,
        string $action,
        User $user,
        array $details = [],
        ?array $beforeState = null,
        ?array $afterState = null,
        string $result = self::RESULT_SUCCESS,
        ?string $errorMessage = null,
    ): BotActionLog {
        return self::log($bot, $action, ActionSource::Api, $user, $details, $beforeState, $afterState, null, $result, $errorMessage);
    }

    public static function logAgentAction(
        Bot $bot,
        string $action,
        array $details = [],
        ?int $conversationId = null,
        ?array $beforeState = null,
        ?array $afterState = null,
        string $result = self::RESULT_SUCCESS,
        ?string $errorMessage = null,
    ): BotActionLog {
        return self::log($bot, $action, ActionSource::Agent, null, $details, $beforeState, $afterState, $conversationId, $result, $errorMessage);
    }

    public static function logSystemAction(
        Bot $bot,
        string $action,
        array $details = [],
        ?array $beforeState = null,
        ?array $afterState = null,
        string $result = self::RESULT_SUCCESS,
        ?string $errorMessage = null,
    ): BotActionLog {
        return self::log($bot, $action, ActionSource::System, null, $details, $beforeState, $afterState, null, $result, $errorMessage);
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
