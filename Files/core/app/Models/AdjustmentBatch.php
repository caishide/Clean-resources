<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjustmentBatch extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'batch_key',
        'reason_type',
        'reference_type',
        'reference_id',
        'finalized_by',
        'finalized_at',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'finalized_at' => 'datetime',
    ];

    public function entries()
    {
        return $this->hasMany(AdjustmentEntry::class, 'batch_id');
    }
}
