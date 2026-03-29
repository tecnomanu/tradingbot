<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->string('agent_state')->nullable()->after('ai_agent_enabled');
            $table->tinyInteger('agent_state_streak')->unsigned()->default(0)->after('agent_state');
            $table->timestamp('ai_next_consultation_at')->nullable()->after('agent_state_streak');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn(['agent_state', 'agent_state_streak', 'ai_next_consultation_at']);
        });
    }
};
