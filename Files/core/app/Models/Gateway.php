<?php

namespace App\Models;

use App\Constants\Status;
use App\Traits\GlobalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gateway - 支付网关模型
 *
 * 管理自动和手动支付网关配置
 */
class Gateway extends Model
{
    use GlobalStatus;

    /** @var int 手动网关最小代码 */
    private const MANUAL_GATEWAY_MIN_CODE = 1000;

    /**
     * 隐藏的属性
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'gateway_parameters', 'extra'
    ];

    /**
     * 属性类型转换
     *
     * @var array<string, string>
     */
    protected $casts = [
        'code' => 'string',
        'extra' => 'object',
        'input_form' => 'object',
        'supported_currencies' => 'object'
    ];

    /**
     * 获取网关的所有货币
     *
     * @return HasMany
     */
    public function currencies(): HasMany
    {
        return $this->hasMany(GatewayCurrency::class, 'method_code', 'code');
    }

    /**
     * 获取网关的表单
     *
     * @return BelongsTo
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * 获取网关的单一货币(最新)
     *
     * @return HasOne
     */
    public function singleCurrency(): HasOne
    {
        return $this->hasOne(GatewayCurrency::class, 'method_code', 'code')->orderBy('id', 'desc');
    }

    /**
     * 获取货币类型(加密/法币)
     *
     * @return string
     */
    public function scopeCrypto(): string
    {
        return $this->crypto == Status::ENABLE ? 'crypto' : 'fiat';
    }

    /**
     * 筛选自动网关
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('code', '<', self::MANUAL_GATEWAY_MIN_CODE);
    }

    /**
     * 筛选手动网关
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeManual(Builder $query): Builder
    {
        return $query->where('code', '>=', self::MANUAL_GATEWAY_MIN_CODE);
    }
}
