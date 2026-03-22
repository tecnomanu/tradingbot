<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bot_action_logs', function (Blueprint $table) {
            $table->string('result', 20)->default('success')->after('after_state');
            $table->string('error_message', 1000)->nullable()->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('bot_action_logs', function (Blueprint $table) {
            $table->dropColumn(['result', 'error_message']);
        });
    }
};
