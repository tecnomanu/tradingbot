<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->json('risk_config')->nullable()->after('ai_notify_events');
            $table->string('risk_guard_reason', 500)->nullable()->after('risk_config');
            $table->timestamp('risk_guard_triggered_at')->nullable()->after('risk_guard_reason');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn(['risk_config', 'risk_guard_reason', 'risk_guard_triggered_at']);
        });
    }
};
