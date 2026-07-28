<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $table = 'packages';

    protected $fillable = [
        'name',
        'price',
        'transaction_limit',
        'decentralized_wallet_limit',
        'domain_limit',
        'duration',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
        'transaction_limit' => 'integer',
        'decentralized_wallet_limit' => 'integer',
        'domain_limit' => 'integer',
        'duration' => 'integer',
        'status' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(MerchantSubscription::class);
    }
}
