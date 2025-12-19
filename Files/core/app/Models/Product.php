<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Product - Represents a product in the e-commerce system
 *
 * Manages product information including pricing, inventory, and categorization.
 */
class Product extends Model
{
    use GlobalStatus;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'name',
        'price',
        'quantity',
        'description',
        'meta_title',
        'meta_description',
        'meta_keyword',
        'thumbnail',
        'specifications',
        'bv',
        'is_featured',
        'status',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'specifications'    => 'array',
        'meta_keyword'      => 'array',
    ];


    /**
     * Get the category that the product belongs to
     *
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the images associated with the product
     *
     * @return HasMany
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id');
    }

    /**
     * Get the featured status badge HTML
     *
     * @return Attribute
     */
    public function statusFeature(): Attribute
    {
        return new Attribute(function (): string {
            $html = '';
            if ($this->is_featured == Status::ENABLE) {
                $html = '<span class="badge badge--success">' . trans('Featured') . '</span>';
            } else {
                $html = '<span class="badge badge--warning">' . trans('UnFeatured') . '</span>';
            }
            return $html;
        });
    }

    /**
     * Scope to filter products that have an active category
     *
     * @param Builder $q
     * @return Builder
     */
    public function scopeHasCategory(Builder $q): Builder
    {
        return $q->whereHas('category', function ($q) {
            $q->active();
        });
    }
}
