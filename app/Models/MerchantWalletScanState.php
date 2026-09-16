<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantWalletScanState extends Model
{
    protected $fillable = [
        'merchant_user_wallet_id',
        'chain_list_id',
        'last_scanned_block',
        'last_scanned_at',
    ];

    protected $casts = [
        'last_scanned_block' => 'integer',
        'last_scanned_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(
            MerchantUserWallet::class,
            'merchant_user_wallet_id'
        );
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(
            ChainList::class,
            'chain_list_id'
        );
    }
}
