<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenList extends Model
{
    use HasFactory;

    protected $table = 'token_list';

    protected $fillable = [
        'chain_id',
        'icon',
        'token_name',
        'symbol',
        'contract_address',
        'status',
    ];

    public function chain()
    {
        return $this->belongsTo(ChainList::class, 'chain_id');
    }
}
