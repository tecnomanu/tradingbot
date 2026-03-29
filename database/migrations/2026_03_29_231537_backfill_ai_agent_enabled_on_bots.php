<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set all existing bots with null ai_agent_enabled to true (enabled)
        DB::table('bots')->whereNull('ai_agent_enabled')->update(['ai_agent_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-reversible data backfill
    }
};
