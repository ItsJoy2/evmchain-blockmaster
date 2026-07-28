<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_subscriptions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('transaction_limit');
            $table->unsignedInteger('used_transactions')->default(0);
            $table->unsignedInteger('decentralized_wallet_limit');
            $table->unsignedInteger('used_decentralized_wallets')->default(0);
            $table->unsignedInteger('domain_limit');
            $table->unsignedInteger('used_domains')->default(0);
            $table->timestamp('started_at');
            $table->dateTime('expires_at');
            $table->boolean('status')->default(true);
            $table->boolean('n5')->default(false);
            $table->boolean('n3')->default(false);
            $table->boolean('nexp')->default(false);
            $table->boolean('ntx')->default(false);
            $table->boolean('nwl')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_subscriptions');
    }
};
