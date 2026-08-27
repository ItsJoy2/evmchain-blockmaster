<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockchainDeposit extends Model
{
    protected $fillable = [

        'merchant_id',
        'merchant_user_wallet_id',
        'chain_id',
        'chain_name',
        'network_standard',
        'token_name',
        'token_symbol',
        'contract_address',
        'token_decimals',
        'raw_amount',
        'amount',
        'type',
        'tx_hash',
        'block_number',
        'log_index',
        'from_address',
        'to_address',
        'status',
        'webhook_status',
        'transfer_status',
        'transfer_tx_hash',
        'error_message',
        'detected_at',
        'confirmed_at',
        'credited_at',
        'transferred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:18',

        'detected_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'credited_at' => 'datetime',
        'transferred_at' => 'datetime',
    ];

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'merchant_id'
        );
    }

    public function userWallet(): BelongsTo
    {
        return $this->belongsTo(
            MerchantUserWallet::class,
            'merchant_user_wallet_id'
        );
    }
}
