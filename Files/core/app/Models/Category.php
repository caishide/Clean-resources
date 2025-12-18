<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use GlobalStatus;


    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function scopeHasActiveProduct($query)
    {
        return $query->whereHas('products', function ($query) {
            $query->active();
        });
    }
}
