<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->decimal('stop_loss_price', 16, 8)->nullable()->after('slippage');
            $table->decimal('take_profit_price', 16, 8)->nullable()->after('stop_loss_price');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn(['stop_loss_price', 'take_profit_price']);
        });
    }
};
