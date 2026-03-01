<?php

namespace App\Constants;

final class GridConstants
{
    public const int MIN_GRIDS = 2;
    public const int MAX_GRIDS = 500;
    public const int MIN_LEVERAGE = 1;
    public const int MAX_LEVERAGE = 125;
    public const float RECOMMENDED_SLIPPAGE = 0.1;
    public const float DEFAULT_COMMISSION_RATE = 0.04; // Binance Futures maker/taker fee (%)
    public const float MIN_INVESTMENT = 10.0; // Minimum investment in USDT
}
