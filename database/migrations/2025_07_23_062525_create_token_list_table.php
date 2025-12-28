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
        Schema::create('token_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chain_id')->constrained('chain_list')->cascadeOnDelete();
            $table->string('icon');
            $table->string('token_name',40);
            $table->string('symbol',15);
            $table->string('contract_address',100);
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_list');
    }
};
