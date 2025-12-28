<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChainList extends Model
{
    protected $table = 'chain_list';
    protected $fillable = [
        'chain_id',
        'icon',
        'chain_name',
        'chain_rpc_url',
        'status',
    ];

    public function token(): HasMany
    {
       return $this->hasMany(TokenList::class,'chain_id','id');
    }
}
