<?php

namespace App\Enums;

enum ActionSource: string
{
    case Agent = 'agent';
    case User = 'user';
    case Api = 'api';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Agent => 'Agente AI',
            self::User => 'Usuario',
            self::Api => 'API',
            self::System => 'Sistema',
        };
    }
}
