<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('binance_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('symbol'); // e.g. BTCUSDT
            $table->string('side'); // long, short, neutral
            $table->string('status')->default('pending');

            // Grid configuration
            $table->decimal('price_lower', 18, 8);
            $table->decimal('price_upper', 18, 8);
            $table->unsignedInteger('grid_count');
            $table->decimal('investment', 18, 4);
            $table->unsignedInteger('leverage')->default(1);
            $table->decimal('slippage', 5, 2)->default(0.10);

            // Calculated values
            $table->decimal('real_investment', 18, 4)->nullable();
            $table->decimal('additional_margin', 18, 4)->nullable();
            $table->decimal('est_liquidation_price', 18, 8)->nullable();
            $table->decimal('profit_per_grid', 8, 4)->nullable(); // percentage
            $table->decimal('commission_per_grid', 8, 4)->nullable(); // percentage

            // Aggregated PNL
            $table->decimal('total_pnl', 18, 4)->default(0);
            $table->decimal('grid_profit', 18, 4)->default(0);
            $table->decimal('trend_pnl', 18, 4)->default(0);
            $table->unsignedInteger('total_rounds')->default(0);
            $table->unsignedInteger('rounds_24h')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
