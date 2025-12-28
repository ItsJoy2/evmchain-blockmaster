<?php

use App\Models\DomainLicense;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('domain_license', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('package_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->string('domain');
            $table->string('license_key',100)->unique();
            $table->dateTime('register_at');
            $table->dateTime('expires_at');
            $table->timestamps();
        });
    }

    public function generateLicenseKey(int $length = 25): string
    {
        do {
            $key = strtoupper(Str::random($length));

            $key = substr(chunk_split($key, 5, '-'), 0, -1);

        } while (DomainLicense::where('license_key', $key)->exists());

        return $key;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_license');
    }
};
