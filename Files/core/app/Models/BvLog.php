<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * BvLog - BV(业务量)日志模型
 *
 * 记录用户的BV变动历史，包括左右区位和增减类型
 */
class BvLog extends Model
{
    /** @var string BV增加类型 */
    private const TRX_TYPE_PLUS = '+';

    /** @var string BV减少类型 */
    private const TRX_TYPE_MINUS = '-';

    /**
     * 获取所属用户
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取位置徽章HTML
     *
     * @return Attribute
     */
    public function positionBadge(): Attribute
    {
        return new Attribute(function (): string {
            $html = '';
            if ($this->position == Status::LEFT) {
                $html = '<span class="badge badge--success">' . trans('Left') . '</span>';
            } else {
                $html = '<span class="badge badge--primary">' . trans('Right') . '</span>';
            }
            return $html;
        });
    }

    /**
     * 筛选左区BV记录
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeLeftBV(Builder $query): Builder
    {
        return $query->where('position', Status::LEFT)->where('trx_type', self::TRX_TYPE_PLUS);
    }

    /**
     * 筛选右区BV记录
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeRightBV(Builder $query): Builder
    {
        return $query->where('position', Status::RIGHT)->where('trx_type', self::TRX_TYPE_PLUS);
    }

    /**
     * 筛选扣减BV记录
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeCutBV(Builder $query): Builder
    {
        return $query->where('trx_type', self::TRX_TYPE_MINUS);
    }

    /**
     * 筛选已付BV记录
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePaidBV(Builder $query): Builder
    {
        return $query->where('trx_type', self::TRX_TYPE_PLUS);
    }
}
