<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blockchain_deposits', function (Blueprint $table) {

            $table->id();
            $table->foreignId('merchant_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('merchant_user_wallet_id')->constrained('merchant_user_wallets')->cascadeOnDelete();
            $table->unsignedBigInteger('chain_id');
            $table->string('chain_name', 100)->nullable();
            $table->string('network_standard', 50)->nullable();
            $table->string('token_name', 100);
            $table->string('token_symbol', 50)->nullable();
            $table->string('contract_address', 255)->nullable();
            $table->unsignedInteger('token_decimals')->default(18);
            $table->decimal('raw_amount', 16, 9);
            $table->decimal('amount', 16, 9);
            $table->enum('type', ['native','token',
            ])->default('token');
            $table->string('tx_hash', 255);
            $table->unsignedBigInteger('block_number')->nullable();
            $table->unsignedBigInteger('log_index')->nullable();
            $table->string('from_address', 255)->nullable();
            $table->string('to_address', 255)->nullable();
            $table->enum('status', ['detected','confirmed','credited','transferring','transferred','failed',
            ])->default('detected');
            $table->enum('webhook_status', ['pending','processing',])->default('pending');
            $table->enum('transfer_status', ['pending','processing','completed','failed',])->default('pending');
            $table->string('transfer_tx_hash', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('credited_at')->nullable();
            $table->timestamp('transferred_at')->nullable();
            $table->timestamps();
            $table->unique(
                [
                    'chain_id',
                    'tx_hash',
                    'log_index',
                ],
                'chain_tx_log_unique'
            );

            $table->index('merchant_id');
            $table->index('merchant_user_wallet_id');
            $table->index('chain_id');
            $table->index('contract_address');
            $table->index(['status','transfer_status',]);
            $table->index(['webhook_status',]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blockchain_deposits');
    }
};
