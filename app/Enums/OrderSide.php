<?php

namespace App\Enums;

enum OrderSide: string
{
    case Buy = 'buy';
    case Sell = 'sell';

    public function label(): string
    {
        return match ($this) {
            self::Buy => 'Compra',
            self::Sell => 'Venta',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Buy => 'green',
            self::Sell => 'red',
        };
    }
}
