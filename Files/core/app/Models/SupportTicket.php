<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

/**
 * SupportTicket - 客服工单模型
 *
 * 管理用户支持工单及其消息交互
 */
class SupportTicket extends Model
{
    /**
     * 获取用户全名
     *
     * @return Attribute
     */
    public function fullname(): Attribute
    {
        return new Attribute(
            get: fn() => $this->name,
        );
    }

    /**
     * 获取用户名(邮箱)
     *
     * @return Attribute
     */
    public function username(): Attribute
    {
        return new Attribute(
            get: fn() => $this->email,
        );
    }

    /**
     * 获取状态徽章HTML
     *
     * @return Attribute
     */
    public function statusBadge(): Attribute
    {
        return new Attribute(function (): string {
            $html = '';
            if ($this->status == Status::TICKET_OPEN) {
                $html = '<span class="badge badge--success">' . trans("Open") . '</span>';
            } elseif ($this->status == Status::TICKET_ANSWER) {
                $html = '<span class="badge badge--primary">' . trans("Answered") . '</span>';
            } elseif ($this->status == Status::TICKET_REPLY) {
                $html = '<span class="badge badge--warning">' . trans("Customer Reply") . '</span>';
            } elseif ($this->status == Status::TICKET_CLOSE) {
                $html = '<span class="badge badge--dark">' . trans("Closed") . '</span>';
            }
            return $html;
        });
    }

    /**
     * 获取工单所属用户
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取工单的所有消息
     *
     * @return HasMany
     */
    public function supportMessage(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    /**
     * 筛选待处理工单
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [Status::TICKET_OPEN, Status::TICKET_REPLY]);
    }

    /**
     * 筛选已关闭工单
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', Status::TICKET_CLOSE);
    }

    /**
     * 筛选已回复工单
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeAnswered(Builder $query): Builder
    {
        return $query->where('status', Status::TICKET_ANSWER);
    }
}
