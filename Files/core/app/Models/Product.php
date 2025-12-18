<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Product extends Model
{
    use GlobalStatus;

    protected $casts = [
        'specifications'    => 'array',
        'meta_keyword'      => 'array',
    ];


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    public function statusFeature(): Attribute
    {
        return new Attribute(function () {
            $html = '';
            if ($this->is_featured == Status::ENABLE) {
                $html = '<span class="badge badge--success">' . trans('Featured') . '</span>';
            } else {
                $html = '<span class="badge badge--warning">' . trans('UnFeatured') . '</span>';
            }
            return $html;
        });
    }

    public function scopeHasCategory($q)
    {
        return $q->whereHas('category', function ($q) {
            $q->active();
        });
    }
}
