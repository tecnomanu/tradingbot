<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('running'); // running, completed, failed
            $table->string('trigger')->default('scheduled'); // scheduled, manual
            $table->string('model')->nullable();
            $table->text('summary')->nullable();
            $table->integer('total_tokens')->default(0);
            $table->integer('total_tool_calls')->default(0);
            $table->integer('total_messages')->default(0);
            $table->integer('duration_ms')->nullable();
            $table->json('actions_taken')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
