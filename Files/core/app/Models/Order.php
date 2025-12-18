<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Order extends Model
{
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function statusOrderBadge(): Attribute
    {
        return new Attribute(function () {
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
