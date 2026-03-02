<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->text('ai_system_prompt')->nullable()->after('take_profit_price');
            $table->text('ai_user_prompt')->nullable()->after('ai_system_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn(['ai_system_prompt', 'ai_user_prompt']);
        });
    }
};
