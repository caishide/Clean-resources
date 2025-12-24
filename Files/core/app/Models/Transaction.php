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
        'reversal_of_id',
        'adjustment_batch_id',
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

    public function setDetailsAttribute($value): void
    {
        $this->attributes['details'] = $this->normalizeDetails($value);
    }

    private function normalizeDetails($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return json_encode(['text' => ''], JSON_UNESCAPED_UNICODE);
            }
            if ($this->isJsonString($trimmed)) {
                return $trimmed;
            }
            return json_encode(['text' => $value], JSON_UNESCAPED_UNICODE);
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return json_encode(['text' => (string) $value], JSON_UNESCAPED_UNICODE);
    }

    private function isJsonString(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

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
