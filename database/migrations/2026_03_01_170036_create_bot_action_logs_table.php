<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->string('action'); // order_placed, order_cancelled, grid_adjusted, sl_set, tp_set, bot_stopped, leverage_changed, position_closed
            $table->string('source'); // agent, system, manual
            $table->json('details')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_action_logs');
    }
};
