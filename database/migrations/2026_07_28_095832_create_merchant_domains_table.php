<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_domains', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('merchant_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->unique();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->index(['merchant_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_domains');
    }
};
