<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            // Source: Binance positionInformationV3 → marginType ("cross" / "isolated").
            // Persisted at bot initialization and synced on each processBot cycle.
            $table->string('margin_type', 20)->nullable()->after('leverage');
        });
    }

    public function down(): void
    {
        Schema::table('bots', function (Blueprint $table) {
            $table->dropColumn('margin_type');
        });
    }
};
