<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_pnl_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_pnl', 18, 4);
            $table->decimal('grid_profit', 18, 4);
            $table->decimal('trend_pnl', 18, 4);
            $table->decimal('unrealized_pnl', 18, 4)->default(0);
            $table->timestamp('snapshot_at');
            $table->timestamps();

            $table->index(['bot_id', 'snapshot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_pnl_snapshots');
    }
};
