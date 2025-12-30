<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('general_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name');
            $table->string('logo')->nullable();
            $table->string('favicon')->nullable();
            $table->timestamps();
        });

        DB::table('general_settings')->insert([
            'app_name' => '3Twenty Pay',
            'logo' => 'Web3Twenty',
            'favicon' => 'null'
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('general_settings');
    }
};
