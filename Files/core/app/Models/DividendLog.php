<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DividendLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quarter_key',
        'user_id',
        'pool_type',
        'shares',
        'score',
        'dividend_amount',
        'status',
        'reason',
        'trx_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
