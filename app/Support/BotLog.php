<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class BotLog
{
    public static function info(string $message, array $context = []): void
    {
        Log::channel('bots')->info($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        Log::channel('bots')->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::channel('bots')->warning($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        Log::channel('bots')->debug($message, $context);
    }
}
