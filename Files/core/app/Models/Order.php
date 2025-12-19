<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order - Represents a product order in the system
 *
 * Tracks product orders including quantity, pricing, and status.
 */
class Order extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price',
        'total_price',
        'trx',
        'status',
    ];

    /**
     * Get the product associated with the order
     *
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who placed the order
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the status badge HTML for the order
     *
     * @return Attribute
     */
    public function statusOrderBadge(): Attribute
    {
        return new Attribute(function (): string {
            $html = '';
            if ($this->status == Status::ORDER_PENDING) {
                $html = '<span class="badge badge--warning">' . trans("Pending") . '</span>';
            } elseif ($this->status == Status::ORDER_SHIPPED) {
                $html = '<span class="badge badge--success">' . trans("Shipped") . '</span>';
            } else {
                $html = '<span class="badge badge--danger">' . trans("Cancelled") . '</span>';
            }
            return $html;
        });
    }
}
