<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deposit - Represents user deposit transactions
 *
 * Manages all deposit operations including automatic and manual gateways.
 */
class Deposit extends Model
{
    /** @var int Minimum code for manual gateways */
    private const MANUAL_GATEWAY_MIN_CODE = 1000;

    /** @var int Maximum code for manual gateways (exclusive) */
    private const MANUAL_GATEWAY_MAX_CODE = 5000;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'detail' => 'object'
    ];

    /**
     * The attributes that should be hidden.
     *
     * @var array<int, string>
     */
    protected $hidden = ['detail'];

    /**
     * Get the user that made the deposit
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the gateway used for the deposit
     *
     * @return BelongsTo
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'method_code', 'code');
    }

    /**
     * Get the method name for the deposit
     *
     * @return string|null
     */
    public function methodName(): ?string
    {
        if ($this->method_code < self::MANUAL_GATEWAY_MAX_CODE) {
            $methodName = @$this->gatewayCurrency()->name;
        } else {
            $methodName = 'Google Pay';
        }
        return $methodName;
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
            } elseif ($this->status == Status::PAYMENT_SUCCESS && $this->method_code >= self::MANUAL_GATEWAY_MIN_CODE && $this->method_code <= self::MANUAL_GATEWAY_MAX_CODE) {
                $html = '<span><span class="badge badge--success">' . trans('Approved') . '</span><br>' . diffForHumans($this->updated_at) . '</span>';
            } elseif ($this->status == Status::PAYMENT_SUCCESS && ($this->method_code < self::MANUAL_GATEWAY_MIN_CODE || $this->method_code >= self::MANUAL_GATEWAY_MAX_CODE)) {
                $html = '<span class="badge badge--success">' . trans('Succeed') . '</span>';
            } elseif ($this->status == Status::PAYMENT_REJECT) {
                $html = '<span><span class="badge badge--danger">' . trans('Rejected') . '</span><br>' . diffForHumans($this->updated_at) . '</span>';
            } else {
                $html = '<span class="badge badge--dark">' . trans('Initiated') . '</span>';
            }
            return $html;
        });
    }

    /**
     * Get the gateway currency for this deposit
     *
     * @return GatewayCurrency|null
     */
    public function gatewayCurrency(): ?GatewayCurrency
    {
        return GatewayCurrency::where('method_code', $this->method_code)->where('currency', $this->method_currency)->first();
    }

    /**
     * Get the base currency for the deposit
     *
     * @return string
     */
    public function baseCurrency(): string
    {
        return @$this->gateway->crypto == Status::ENABLE ? 'USD' : $this->method_currency;
    }

    /**
     * Scope to filter pending deposits
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('method_code', '>=', self::MANUAL_GATEWAY_MIN_CODE)->where('status', Status::PAYMENT_PENDING);
    }

    /**
     * Scope to filter rejected deposits
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('method_code', '>=', self::MANUAL_GATEWAY_MIN_CODE)->where('status', Status::PAYMENT_REJECT);
    }

    /**
     * Scope to filter approved manual deposits
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('method_code', '>=', self::MANUAL_GATEWAY_MIN_CODE)->where('method_code', '<', self::MANUAL_GATEWAY_MAX_CODE)->where('status', Status::PAYMENT_SUCCESS);
    }

    /**
     * Scope to filter all successful deposits
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', Status::PAYMENT_SUCCESS);
    }

    /**
     * Scope to filter initiated deposits
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeInitiated(Builder $query): Builder
    {
        return $query->where('status', Status::PAYMENT_INITIATE);
    }
}
