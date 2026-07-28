<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MerchantDomain extends Model
{
    use HasFactory;

    protected $table = 'merchant_domains';
    protected $fillable = [
        'user_id',
        'merchant_subscription_id',
        'domain',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];
    protected $hidden = [
        'merchant_subscription_id',
        'user_id',
        'created_at',
        'updated_at',
        'verified_at',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscription()
    {
        return $this->belongsTo(MerchantSubscription::class, 'merchant_subscription_id');
    }
}
