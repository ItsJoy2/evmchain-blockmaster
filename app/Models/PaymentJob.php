<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invoice_id',
        'token_name',
        'chain_id',
        'wallet_address',
        'key',
        'webhook_url',
        'rpc_url',
        'type',
        'contract_address',
        'tx_hash',
        'amount',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
