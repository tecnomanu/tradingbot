<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_agent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('symbol', 20);
            $table->string('action'); // analyze, adjust_grid, suggest_sl, suggest_tp, alert
            $table->string('signal')->nullable(); // bullish, bearish, neutral, hold
            $table->float('confidence')->nullable(); // 0-1
            $table->json('market_data')->nullable(); // price, volume, indicators snapshot
            $table->text('reasoning')->nullable(); // LLM's explanation
            $table->json('suggestion')->nullable(); // structured suggestion (new range, SL, TP, etc)
            $table->boolean('applied')->default(false);
            $table->string('model')->nullable();
            $table->integer('tokens_used')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agent_logs');
    }
};
