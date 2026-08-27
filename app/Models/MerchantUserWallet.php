<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchantUserWallet extends Model
{
    protected $fillable = [
        'merchant_id',
        'wallet_address',
        'wallet_key',
        'webhook_url',
        'is_active',
        'last_scanned_at',
    ];

    protected $casts = [

        'wallet_key' => 'encrypted',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'last_scanned_at' => 'datetime',
    ];

    protected $hidden = [
        'wallet_key',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'merchant_id'
        );
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(
            BlockchainDeposit::class,
            'merchant_user_wallet_id'
        );
    }
}
