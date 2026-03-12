<?php

use App\Http\Controllers\Api\AiAgentApiController;
use App\Http\Controllers\Api\BotApiController;
use App\Http\Controllers\Api\MarketApiController;
use App\Http\Controllers\Api\OrderApiController;
use App\Http\Controllers\Api\StatusApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| External API Routes – protected by API key
|--------------------------------------------------------------------------
| Auth: X-API-Key: <key>  OR  Authorization: Bearer <key>
| Base URL: /api/v1/
*/

Route::prefix('v1')->middleware('api.key')->group(function () {

    // ------------------------------------------------------------------
    // Status / overview
    // ------------------------------------------------------------------
    Route::get('status', [StatusApiController::class, 'overview']);

    // ------------------------------------------------------------------
    // Bots
    // ------------------------------------------------------------------
    Route::prefix('bots')->group(function () {
        Route::get('/',          [BotApiController::class, 'index']);   // list all
        Route::get('/{bot}',     [BotApiController::class, 'show']);    // full detail
        Route::patch('/{bot}',   [BotApiController::class, 'update']);  // update config
        Route::post('/{bot}/start', [BotApiController::class, 'start']); // start bot
        Route::post('/{bot}/stop',  [BotApiController::class, 'stop']);  // stop bot
    });

    // ------------------------------------------------------------------
    // Market data & analysis (uses same Binance endpoints as the bot)
    // ------------------------------------------------------------------
    Route::prefix('market')->group(function () {
        Route::get('/price',       [MarketApiController::class, 'price']);           // ?symbol=BTCUSDT&testnet=false
        Route::get('/bot/{bot}',   [MarketApiController::class, 'analyze']);         // ?interval=4h&candles=24
    });

    // ------------------------------------------------------------------
    // Orders
    // ------------------------------------------------------------------
    Route::prefix('orders')->group(function () {
        Route::get('/',              [OrderApiController::class, 'global']);  // all orders
        Route::get('/bot/{bot}',     [OrderApiController::class, 'byBot']);   // orders for bot
        Route::get('/bot/{bot}/stats', [OrderApiController::class, 'stats']); // stats for bot
    });

    // ------------------------------------------------------------------
    // AI Agent — conversations, messages, tool calls & action logs
    // ------------------------------------------------------------------
    Route::prefix('ai-agent')->group(function () {
        Route::get('/conversations',                [AiAgentApiController::class, 'conversations']); // ?bot_id=N&limit=20&status=completed
        Route::get('/conversations/{conversation}', [AiAgentApiController::class, 'show']);           // full detail with messages & tool calls
        Route::get('/actions',                      [AiAgentApiController::class, 'actions']);        // ?bot_id=N&limit=30
    });
});
