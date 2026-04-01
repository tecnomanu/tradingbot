<?php

use App\Http\Controllers\AiAgentController;
use App\Http\Controllers\BinanceAccountController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TelegramController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::redirect('/trading', '/bots');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Bots
    Route::resource('bots', BotController::class)->except(['create']);
    Route::post('/bots/{bot}/start', [BotController::class, 'start'])->name('bots.start');
    Route::post('/bots/{bot}/stop', [BotController::class, 'stop'])->name('bots.stop');
    Route::post('/bots/{bot}/reentry', [BotController::class, 'attemptReentry'])->name('bots.reentry');
    Route::post('/bots/calculate-grid', [BotController::class, 'calculateGrid'])->name('bots.calculate-grid');
    Route::post('/bots/current-price', [BotController::class, 'currentPrice'])->name('bots.current-price');

    // Orders & Activity
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/bots', [OrderController::class, 'bots'])->name('bots');
        Route::get('/history', [OrderController::class, 'orderHistory'])->name('history');
        Route::get('/positions', [OrderController::class, 'positions'])->name('positions');
    });

    // AI Agent
    Route::get('/ai-agent', [AiAgentController::class, 'index'])->name('ai-agent.index');
    Route::get('/ai-agent/conversations/{conversation}', [AiAgentController::class, 'showConversation'])->name('ai-agent.conversation');
    Route::post('/ai-agent/consult', [AiAgentController::class, 'runConsultation'])->name('ai-agent.consult');
    Route::put('/ai-agent/bots/{bot}/prompts', [AiAgentController::class, 'updateBotPrompts'])->name('ai-agent.update-prompts');
    Route::post('/ai-agent/bots/{bot}/test-prompts', [AiAgentController::class, 'testBotPrompts'])->name('ai-agent.test-prompts');

    // Binance Accounts
    Route::resource('binance-accounts', BinanceAccountController::class)->except(['create', 'show', 'edit']);
    Route::post('/binance-accounts/{binance_account}/test', [BinanceAccountController::class, 'testConnection'])
        ->name('binance-accounts.test');
    Route::get('/binance-accounts/{binance_account}/balance', [BinanceAccountController::class, 'balance'])
        ->name('binance-accounts.balance');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/rotate-api-key', [ProfileController::class, 'rotateApiKey'])->name('profile.rotate-api-key');

    // Telegram
    Route::post('/telegram/link-token', [TelegramController::class, 'generateLinkToken'])->name('telegram.link-token');
    Route::post('/telegram/disconnect', [TelegramController::class, 'disconnect'])->name('telegram.disconnect');
    Route::post('/telegram/test', [TelegramController::class, 'testMessage'])->name('telegram.test');
    Route::post('/telegram/poll', [TelegramController::class, 'pollUpdates'])->name('telegram.poll');
    Route::post('/telegram/set-chat-id', [TelegramController::class, 'setChatId'])->name('telegram.set-chat-id');
    Route::post('/telegram/setup-webhook', [TelegramController::class, 'setupWebhook'])->name('telegram.setup-webhook');
});

// Telegram webhook (public, no auth)
Route::post('/api/telegram/webhook', [TelegramController::class, 'webhook'])->name('telegram.webhook');

require __DIR__ . '/auth.php';
