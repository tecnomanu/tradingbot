<?php

namespace App\Enums;

enum BotSide: string
{
    case Long = 'long';
    case Short = 'short';
    case Neutral = 'neutral';

    public function label(): string
    {
        return match ($this) {
            self::Long => 'Largo',
            self::Short => 'Corto',
            self::Neutral => 'Neutral',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Long => 'green',
            self::Short => 'red',
            self::Neutral => 'blue',
        };
    }
}
