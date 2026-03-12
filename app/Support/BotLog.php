<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class BotLog
{
    /**
     * Ensure bot_id is in context when provided.
     * Callers should pass bot_id as 3rd param when logging from bot context.
     */
    private static function withBotContext(array $context, ?int $botId): array
    {
        if ($botId !== null) {
            $context = array_merge(['bot_id' => $botId], $context);
        }
        return $context;
    }

    public static function info(string $message, array $context = [], ?int $botId = null): void
    {
        Log::channel('bots')->info($message, self::withBotContext($context, $botId));
    }

    public static function error(string $message, array $context = [], ?int $botId = null): void
    {
        Log::channel('bots')->error($message, self::withBotContext($context, $botId));
    }

    public static function warning(string $message, array $context = [], ?int $botId = null): void
    {
        Log::channel('bots')->warning($message, self::withBotContext($context, $botId));
    }

    public static function debug(string $message, array $context = [], ?int $botId = null): void
    {
        Log::channel('bots')->debug($message, self::withBotContext($context, $botId));
    }
}
