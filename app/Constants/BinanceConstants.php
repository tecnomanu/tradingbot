<?php

namespace App\Constants;

final class BinanceConstants
{
    public const string BASE_URL = 'https://api.binance.com';
    public const string FUTURES_BASE_URL = 'https://fapi.binance.com';
    public const string TESTNET_FUTURES_URL = 'https://testnet.binancefuture.com';

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
}
