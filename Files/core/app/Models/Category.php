<?php

namespace App\Models;

use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * Category - 商品分类模型
 *
 * 管理商品分类及其关联的产品
 */
class Category extends Model
{
    use GlobalStatus, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'image',
        'status',
        'featured',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => 'integer',
        'featured' => 'integer',
    ];

    /**
     * 获取分类下的所有产品
     *
     * @return HasMany
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * 筛选有活跃产品的分类
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeHasActiveProduct(Builder $query): Builder
    {
        return $query->whereHas('products', function ($query) {
            $query->active();
        });
    }
}
