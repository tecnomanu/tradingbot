<?php

namespace App\Constants;

final class BinanceConstants
{
    public const string BASE_URL = 'https://api.binance.com';
    public const string FUTURES_BASE_URL = 'https://fapi.binance.com';
    public const string TESTNET_FUTURES_URL = 'https://testnet.binancefuture.com';

    public const string SIDE_BUY = 'BUY';
    public const string SIDE_SELL = 'SELL';

    public const array SUPPORTED_PAIRS = [
        'BTCUSDT',
        'ETHUSDT',
        'BNBUSDT',
        'SOLUSDT',
        'DOGEUSDT',
        'XRPUSDT',
        'ADAUSDT',
        'AVAXUSDT',
        'DOTUSDT',
        'MATICUSDT',
        'LINKUSDT',
        'UNIUSDT',
        'LTCUSDT',
        'ATOMUSDT',
        'NEARUSDT',
    ];

    public const array LEVERAGE_OPTIONS = [1, 2, 3, 5, 10, 20, 25, 50, 75, 100, 125];

    public const array QUANTITY_PRECISION = [
        'BTCUSDT' => 3,
        'ETHUSDT' => 3,
        'BNBUSDT' => 2,
        'SOLUSDT' => 1,
        'DOGEUSDT' => 0,
        'XRPUSDT' => 1,
        'ADAUSDT' => 0,
        'AVAXUSDT' => 1,
        'DOTUSDT' => 1,
        'MATICUSDT' => 0,
        'LINKUSDT' => 1,
        'UNIUSDT' => 1,
        'LTCUSDT' => 2,
        'ATOMUSDT' => 1,
        'NEARUSDT' => 1,
    ];

    public const array PRICE_PRECISION = [
        'BTCUSDT' => 1,
        'ETHUSDT' => 2,
        'BNBUSDT' => 2,
        'SOLUSDT' => 2,
        'DOGEUSDT' => 5,
        'XRPUSDT' => 4,
        'ADAUSDT' => 5,
        'AVAXUSDT' => 2,
        'DOTUSDT' => 3,
        'MATICUSDT' => 5,
        'LINKUSDT' => 3,
        'UNIUSDT' => 3,
        'LTCUSDT' => 2,
        'ATOMUSDT' => 3,
        'NEARUSDT' => 3,
    ];
}
