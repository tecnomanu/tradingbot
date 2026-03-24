<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('bots')
            ->whereNotNull('risk_guard_reason')
            ->whereNull('stop_reason')
            ->where('status', 'stopped')
            ->update(['stop_reason' => 'risk_guard']);
    }

    public function down(): void
    {
        // Intentionally irreversible — no way to distinguish original nulls
    }
};
