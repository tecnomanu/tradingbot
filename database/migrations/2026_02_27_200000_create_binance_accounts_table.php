<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binance_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('api_key'); // Encrypted
            $table->text('api_secret'); // Encrypted
            $table->boolean('is_testnet')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binance_accounts');
    }
};
