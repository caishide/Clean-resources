<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuarterlySettlement extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quarter_key',
        'start_date',
        'end_date',
        'total_pv',
        'pool_stockist',
        'pool_leader',
        'total_shares',
        'total_score',
        'unit_value_stockist',
        'unit_value_leader',
        'finalized_at',
        'config_snapshot',
    ];
}
