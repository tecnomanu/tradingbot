<?php

namespace App\Enums;

enum AgentTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case SlTpAlert = 'sl_tp_alert';
    case PriceBreakout = 'price_breakout';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programado',
            self::Manual => 'Manual',
            self::SlTpAlert => 'Alerta SL/TP',
            self::PriceBreakout => 'Ruptura de rango',
        };
    }
}
