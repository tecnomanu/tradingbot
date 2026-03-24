<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->string('stop_reason', 30)->nullable()->after('status');
            $table->string('risk_guard_level', 10)->nullable()->after('risk_guard_triggered_at');
            $table->boolean('reentry_enabled')->default(false)->after('risk_guard_level');
            $table->unsignedSmallInteger('reentry_cooldown_minutes')->default(60)->after('reentry_enabled');
            $table->timestamp('reentry_last_attempt_at')->nullable()->after('reentry_cooldown_minutes');
            $table->string('reentry_last_block_reason')->nullable()->after('reentry_last_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn([
                'stop_reason',
                'risk_guard_level',
                'reentry_enabled',
                'reentry_cooldown_minutes',
                'reentry_last_attempt_at',
                'reentry_last_block_reason',
            ]);
        });
    }
};
