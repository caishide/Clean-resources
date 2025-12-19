<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjustmentEntry extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'batch_id',
        'asset_type',
        'user_id',
        'amount',
        'reversal_of_id',
    ];

    public function batch()
    {
        return $this->belongsTo(AdjustmentBatch::class, 'batch_id');
    }
}
