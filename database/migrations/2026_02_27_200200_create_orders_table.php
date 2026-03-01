<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('side'); // buy, sell
            $table->string('status')->default('open');
            $table->decimal('price', 18, 8);
            $table->decimal('quantity', 18, 8);
            $table->unsignedInteger('grid_level');
            $table->decimal('pnl', 18, 4)->nullable(); // Profit from this order
            $table->string('binance_order_id')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->index(['bot_id', 'status']);
            $table->index(['bot_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
