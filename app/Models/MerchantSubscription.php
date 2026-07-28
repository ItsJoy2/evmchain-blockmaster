<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantSubscription extends Model
{
    protected $table = 'merchant_subscriptions';

    protected $fillable = [

        'user_id',
        'package_id',
        'transaction_limit',
        'used_transactions',
        'decentralized_wallet_limit',
        'used_decentralized_wallets',
        'domain_limit',
        'used_domains',
        'started_at',
        'expires_at',
        'status'

    ];

    protected $casts = [
        'transaction_limit' => 'integer',
        'used_transactions' => 'integer',
        'decentralized_wallet_limit' => 'integer',
        'used_decentralized_wallets' => 'integer',
        'domain_limit' => 'integer',
        'used_domains' => 'integer',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'status' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function domains()
    {
        return $this->hasMany(MerchantDomain::class, 'merchant_subscription_id');
    }

}
