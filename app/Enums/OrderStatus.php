<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Open = 'open';
    case Filled = 'filled';
    case Cancelled = 'cancelled';
    case PartiallyFilled = 'partially_filled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Filled => 'Ejecutada',
            self::Cancelled => 'Cancelada',
            self::PartiallyFilled => 'Parcialmente Ejecutada',
        };
    }
}
