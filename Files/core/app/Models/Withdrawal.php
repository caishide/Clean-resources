<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Withdrawal - Represents user withdrawal transactions
 *
 * Manages all withdrawal operations and their status tracking.
 */
class Withdrawal extends Model
{
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'withdraw_information' => 'object'
    ];

    /**
     * The attributes that should be hidden.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'withdraw_information'
    ];

    /**
     * Get the user that made the withdrawal
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the withdrawal method used
     *
     * @return BelongsTo
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(WithdrawMethod::class, 'method_id');
    }

    /**
     * Get the status badge HTML
     *
     * @return Attribute
     */
    public function statusBadge(): Attribute
    {
        return new Attribute(function (): string {
            $html = '';
            if ($this->status == Status::PAYMENT_PENDING) {
                $html = '<span class="badge badge--warning">' . trans('Pending') . '</span>';
            } elseif ($this->status == Status::PAYMENT_SUCCESS) {
                $html = '<span><span class="badge badge--success">' . trans('Approved') . '</span><br>' . diffForHumans($this->updated_at) . '</span>';
            } elseif ($this->status == Status::PAYMENT_REJECT) {
                $html = '<span><span class="badge badge--danger">' . trans('Rejected') . '</span><br>' . diffForHumans($this->updated_at) . '</span>';
            }
            return $html;
        });
    }

    /**
     * Scope to filter pending withdrawals
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', Status::PAYMENT_PENDING);
    }

    /**
     * Scope to filter approved withdrawals
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', Status::PAYMENT_SUCCESS);
    }

    /**
     * Scope to filter rejected withdrawals
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', Status::PAYMENT_REJECT);
    }
}
