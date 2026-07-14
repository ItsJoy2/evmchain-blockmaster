<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_packages', function (Blueprint $table) {

            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('transaction_limit');
            $table->unsignedInteger('used_transactions')->default(0);
            $table->timestamp('started_at');
            $table->dateTime('expires_at');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index(['user_id','status']);

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_packages');
    }
};
