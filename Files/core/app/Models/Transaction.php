<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Transaction - Represents financial transactions in the system
 *
 * Tracks all user balance changes including deposits, withdrawals,
 * purchases, commissions, and transfers.
 */
class Transaction extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'amount',
        'post_balance',
        'charge',
        'trx_type',
        'details',
        'trx',
        'remark',
        'source_type',
        'source_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'post_balance' => 'decimal:2',
        'charge' => 'decimal:2',
    ];

    /**
     * Get the user that owns the transaction
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
