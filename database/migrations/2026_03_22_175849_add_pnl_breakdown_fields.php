<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Per-order fee tracking
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('fee', 12, 4)->nullable()->after('pnl');
        });

        // Aggregate fee tracking on bot
        Schema::table('bots', function (Blueprint $table) {
            $table->decimal('total_fees', 12, 4)->default(0)->after('grid_profit');
        });

        // Fee tracking in PNL snapshots
        Schema::table('bot_pnl_snapshots', function (Blueprint $table) {
            $table->decimal('total_fees', 12, 4)->default(0)->after('grid_profit');
        });

        // Backfill fees for existing filled sell orders:
        // fee = price × quantity × 0.0004 × 2 (estimated 0.04% taker per side, round-trip)
        DB::statement("
            UPDATE orders
            SET fee = ROUND(price * quantity * 0.0004 * 2, 4)
            WHERE status = 'filled'
              AND side = 'sell'
              AND pnl IS NOT NULL
              AND fee IS NULL
        ");

        // Backfill total_fees on bots from their orders
        DB::statement("
            UPDATE bots
            SET total_fees = COALESCE((
                SELECT SUM(o.fee) FROM orders o
                WHERE o.bot_id = bots.id AND o.fee IS NOT NULL
            ), 0)
        ");
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('fee');
        });

        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn('total_fees');
        });

        Schema::table('bot_pnl_snapshots', function (Blueprint $table) {
            $table->dropColumn('total_fees');
        });
    }
};
