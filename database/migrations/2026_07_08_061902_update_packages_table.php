<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {

            $table->unsignedInteger('transaction_limit')->after('price');
            $table->unsignedInteger('decentralized_wallet_limit')->after('transaction_limit');
            $table->unsignedInteger('domain_limit')->after('decentralized_wallet_limit');
            $table->unsignedInteger('duration')->change();
            $table->boolean('status')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {

            $table->dropColumn('transaction_limit', 'decentralized_wallet_limit', 'domain_limit');
        });
    }
};
