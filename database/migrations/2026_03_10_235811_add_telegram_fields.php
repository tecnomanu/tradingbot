<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('api_key');
            $table->string('telegram_link_token', 32)->nullable()->after('telegram_chat_id');
        });

        Schema::table('bots', function (Blueprint $table) {
            $table->boolean('ai_notify_telegram')->default(false)->after('ai_consultation_interval');
            $table->json('ai_notify_events')->nullable()->after('ai_notify_telegram');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_link_token']);
        });

        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn(['ai_notify_telegram', 'ai_notify_events']);
        });
    }
};
