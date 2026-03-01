<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role'); // system, user, assistant, tool
            $table->text('content')->nullable();
            $table->json('tool_calls')->nullable(); // assistant's tool_calls array
            $table->string('tool_call_id')->nullable(); // for tool role messages
            $table->string('tool_name')->nullable();
            $table->json('tool_args')->nullable();
            $table->json('tool_result')->nullable();
            $table->integer('tokens')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversation_messages');
    }
};
