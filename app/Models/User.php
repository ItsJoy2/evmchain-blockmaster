<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'last_login_data',
        'wallet_address',
        'user_secret',
        'two_factor_secret',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'user_secret',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($user) {

            if (empty($user->user_secret)) {

                do {

                    $secret = bin2hex(random_bytes(32));

                } while (
                    self::where('user_secret', $secret)->exists()
                );

                $user->user_secret = $secret;
            }
        });
    }
}
