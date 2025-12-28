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
        Schema::create('payment_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->string('invoice_id')->unique();
            $table->string('token_name')->default('native');
            $table->string('chain_id');
            $table->string('wallet_address');
            $table->longText('key');
            $table->string('webhook_url');
            $table->string('rpc_url');
            $table->enum('type',['native','token']);
            $table->string('contract_address')->nullable();
            $table->string('tx_hash')->nullable();
            $table->integer('amount')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed','expired'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_jobs');
    }
};
