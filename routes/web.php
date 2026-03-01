<?php

use App\Http\Controllers\AiAgentController;
use App\Http\Controllers\BinanceAccountController;
use App\Http\Controllers\BotController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Bots
    Route::resource('bots', BotController::class)->except(['create']);
    Route::post('/bots/{bot}/start', [BotController::class, 'start'])->name('bots.start');
    Route::post('/bots/{bot}/stop', [BotController::class, 'stop'])->name('bots.stop');
    Route::post('/bots/calculate-grid', [BotController::class, 'calculateGrid'])->name('bots.calculate-grid');
    Route::post('/bots/current-price', [BotController::class, 'currentPrice'])->name('bots.current-price');

    // Orders & Activity
    Route::prefix('orders')->name('orders.')->group(function () {
        Route::get('/active-bots', [OrderController::class, 'activeBots'])->name('active-bots');
        Route::get('/bot-history', [OrderController::class, 'botHistory'])->name('bot-history');
        Route::get('/open', [OrderController::class, 'openOrders'])->name('open');
        Route::get('/history', [OrderController::class, 'orderHistory'])->name('history');
        Route::get('/positions', [OrderController::class, 'positions'])->name('positions');
    });

    // AI Agent
    Route::get('/ai-agent', [AiAgentController::class, 'index'])->name('ai-agent.index');
    Route::get('/ai-agent/conversations/{conversation}', [AiAgentController::class, 'showConversation'])->name('ai-agent.conversation');
    Route::post('/ai-agent/consult', [AiAgentController::class, 'runConsultation'])->name('ai-agent.consult');
    Route::post('/ai-agent/quick-analysis', [AiAgentController::class, 'runQuickAnalysis'])->name('ai-agent.quick-analysis');

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
});

require __DIR__ . '/auth.php';
