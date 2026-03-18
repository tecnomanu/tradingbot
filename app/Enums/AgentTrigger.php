<?php

namespace App\Enums;

enum AgentTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';
    case SlTpAlert = 'sl_tp_alert';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programado',
            self::Manual => 'Manual',
            self::SlTpAlert => 'Alerta SL/TP',
        };
    }
}
