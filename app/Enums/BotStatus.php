<?php

namespace App\Enums;

enum BotStatus: string
{
    case Active = 'active';
    case Stopped = 'stopped';
    case Error = 'error';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Stopped => 'Detenido',
            self::Error => 'Error',
            self::Pending => 'Pendiente',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Stopped => 'gray',
            self::Error => 'red',
            self::Pending => 'yellow',
        };
    }
}
