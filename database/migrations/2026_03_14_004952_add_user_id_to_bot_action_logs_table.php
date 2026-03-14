<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_action_logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('bot_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bot_action_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
