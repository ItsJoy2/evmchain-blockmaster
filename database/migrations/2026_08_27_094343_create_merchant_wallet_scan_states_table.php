<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_wallet_scan_states', function (Blueprint $table) {

            $table->id();

            $table->foreignId('merchant_user_wallet_id')
                ->constrained('merchant_user_wallets')
                ->cascadeOnDelete();

            /*
             * This is ChainList.id
             *
             * Example:
             *
             * chain_list:
             * id = 1
             * chain_id = 9996
             */
            $table->foreignId('chain_list_id')
                ->constrained('chain_list')
                ->cascadeOnDelete();

            /*
             * Last successfully scanned blockchain block.
             */
            $table->unsignedBigInteger('last_scanned_block')
                ->nullable();

            $table->timestamp('last_scanned_at')
                ->nullable();

            $table->timestamps();

            /*
             * One scanner state per wallet + chain.
             */
            $table->unique(
                ['merchant_user_wallet_id', 'chain_list_id'],
                'wallet_chain_scan_unique'
            );

            $table->index('merchant_user_wallet_id');
            $table->index('chain_list_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_wallet_scan_states');
    }
};
