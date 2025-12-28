<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static create(array $array)
 */
class PaymentJobs extends Model
{
    protected $table = 'payment_jobs';
    protected $fillable = [
        'token_name',
        'chain_id',
        'wallet_address',
        'status',
        'key',
        'webhook_url',
        'rpc_url',
        'type',
        'contract_address',
        'invoice_id',
        'user_id',
        'amount',
    ];

    public static function generateUIDCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 14));
        } while (self::where('invoice_id', $code)->exists());

        return $code;
    }
    protected $hidden = [
        'key'
    ];
}
