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
            $table->unsignedInteger('duration')->change();
            $table->boolean('status')->default(true)->change();
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {

            $table->dropColumn('transaction_limit');
        });
    }
};
