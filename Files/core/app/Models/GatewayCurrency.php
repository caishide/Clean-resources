<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GatewayCurrency - 网关货币配置模型
 *
 * 管理支付网关支持的货币及其配置
 */
class GatewayCurrency extends Model
{
    /**
     * 隐藏的属性
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'gateway_parameter'
    ];

    /**
     * 属性类型转换
     *
     * @var array<string, string>
     */
    protected $casts = ['status' => 'boolean'];

    /**
     * 获取所属网关
     *
     * @return BelongsTo
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'method_code', 'code');
    }

    /**
     * 获取货币标识符
     *
     * @return string
     */
    public function currencyIdentifier(): string
    {
        return $this->name ?? $this->method->name . ' ' . $this->currency;
    }

    /**
     * 获取基础货币代码
     *
     * @return string
     */
    public function scopeBaseCurrency(): string
    {
        return $this->method->crypto == Status::ENABLE ? 'USD' : $this->currency;
    }

    /**
     * 获取基础货币符号
     *
     * @return string
     */
    public function scopeBaseSymbol(): string
    {
        return $this->method->crypto == Status::ENABLE ? '$' : $this->symbol;
    }
}
