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
        'duration',
        'status',
    ];

    protected $casts = [
        'price' => 'float',
        'transaction_limit' => 'integer',
        'duration' => 'integer',
        'status' => 'boolean',
    ];

    public function users()
    {
        return $this->hasMany(UserPackage::class);
    }
}
