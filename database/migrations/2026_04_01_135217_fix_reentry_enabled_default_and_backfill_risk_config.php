<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Change column default to true for all future bots.
        Schema::table('bots', function (Blueprint $table) {
            $table->boolean('reentry_enabled')->default(true)->change();
        });

        // Backfill: enable reentry on every existing bot.
        DB::table('bots')->update(['reentry_enabled' => true]);

        // Backfill risk_config: ensure pause_and_rebuild is the hard_guard_action
        // for any bot whose risk_config is null or does not set hard_guard_action.
        $bots = DB::table('bots')->get(['id', 'risk_config']);
        foreach ($bots as $bot) {
            $config = $bot->risk_config ? json_decode($bot->risk_config, true) : [];
            if (empty($config['hard_guard_action'])) {
                $config['hard_guard_action'] = 'pause_and_rebuild';
                DB::table('bots')->where('id', $bot->id)->update([
                    'risk_config' => json_encode($config),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->boolean('reentry_enabled')->default(false)->change();
        });
    }
};
