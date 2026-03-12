<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('filled_at');
            $table->index(['bot_id', 'grid_level']);
            $table->index('binance_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['filled_at']);
            $table->dropIndex(['bot_id', 'grid_level']);
            $table->dropIndex(['binance_order_id']);
        });
    }
};
