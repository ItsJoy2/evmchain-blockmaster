<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_user_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->cascadeOnDelete();
            $table->string('wallet_address', 255)->unique();
            $table->text('wallet_key');
            $table->text('webhook_url');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_scanned_at')->nullable();
            $table->timestamps();
            $table->index('merchant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_user_wallets');
    }
};
