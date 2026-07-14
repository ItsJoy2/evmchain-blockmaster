<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPackage extends Model
{
    protected $table = 'user_packages';

    protected $fillable = [

        'user_id',
        'package_id',
        'transaction_limit',
        'used_transactions',
        'started_at',
        'expires_at',
        'status'

    ];

}
