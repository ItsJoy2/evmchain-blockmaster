<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_jobs', function (Blueprint $table) {
            $table->decimal('received_amount', 36, 8)
                ->nullable()
                ->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payment_jobs', function (Blueprint $table) {
            $table->dropColumn('received_amount');
        });
    }
};
