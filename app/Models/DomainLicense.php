<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DomainLicense extends Model
{
    use HasFactory;

    protected $table = 'domain_license';

    protected $fillable = [
        'user_id',
        'package_id',
        'domain',
        'license_key',
        'register_at',
        'expires_at',
    ];

    // Auto license generate
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($license) {
            if (empty($license->license_key)) {
                $license->license_key = self::generateLicenseKey();
            }
        });
    }

    public static function generateLicenseKey(int $length = 25): string
    {
        do {
            $key = strtoupper(Str::random($length));
            $key = substr(chunk_split($key, 5, '-'), 0, -1);
        } while (self::where('license_key', $key)->exists());

        return $key;
    }

    public function package(){
        return $this->belongsTo(Package::class);
    }
}
