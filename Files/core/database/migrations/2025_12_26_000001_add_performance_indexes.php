<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 性能优化：添加数据库索引
 * 
 * 此迁移为关键表添加索引，以提升查询性能
 * 基于 CODE_REVIEW_REPORT.md 的性能分析结果
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pv_ledger 表索引优化
        if (Schema::hasTable('pv_ledger')) {
            // 用户和位置复合索引（用于 getUserPVBalance 等查询）
            Schema::table('pv_ledger', function (Blueprint $table) {
                $table->index(['user_id', 'position'], 'idx_pv_user_position');
            });

            // 来源类型和来源ID复合索引（用于按来源查询）
            Schema::table('pv_ledger', function (Blueprint $table) {
                $table->index(['source_type', 'source_id'], 'idx_pv_source');
            });

            // 用户和交易类型复合索引（用于聚合计算）
            Schema::table('pv_ledger', function (Blueprint $table) {
                $table->index(['user_id', 'trx_type'], 'idx_pv_user_trx_type');
            });

            // 创建时间索引（用于时间范围查询）
            Schema::table('pv_ledger', function (Blueprint $table) {
                $table->index('created_at', 'idx_pv_created_at');
            });

            // 调整批次ID索引（用于退款调整查询）
            Schema::table('pv_ledger', function (Blueprint $table) {
                $table->index('adjustment_batch_id', 'idx_pv_adjustment_batch');
            });

            // 冲正关联索引（用于追溯原始记录）
            Schema::table('pv_ledger', function (Blueprint $table) {
                $table->index('reversal_of_id', 'idx_pv_reversal_of');
            });
        }

        // transactions 表索引优化
        if (Schema::hasTable('transactions')) {
            // 用户和备注复合索引（用于查询用户奖金）
            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['user_id', 'remark'], 'idx_trx_user_remark');
            });

            // 来源类型和来源ID复合索引（用于按来源查询）
            Schema::table('transactions', function (Blueprint $table) {
                $table->index(['source_type', 'source_id'], 'idx_trx_source');
            });

            // 创建时间索引（用于时间范围查询）
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('created_at', 'idx_trx_created_at');
            });

            // 调整批次ID索引（用于退款调整查询）
            Schema::table('transactions', function (Blueprint $table) {
                $table->index('adjustment_batch_id', 'idx_trx_adjustment_batch');
            });
        }

        // weekly_settlements 表索引优化
        if (Schema::hasTable('weekly_settlements')) {
            // 周键唯一索引（如果不存在）
            Schema::table('weekly_settlements', function (Blueprint $table) {
                $table->unique('week_key', 'unique_week_key');
            });

            // 开始日期索引（用于时间范围查询）
            Schema::table('weekly_settlements', function (Blueprint $table) {
                $table->index('start_date', 'idx_ws_start_date');
            });

            // 结算状态索引（用于查询已结算的周）
            Schema::table('weekly_settlements', function (Blueprint $table) {
                $table->index('finalized_at', 'idx_ws_finalized_at');
            });
        }

        // weekly_settlement_user_summaries 表索引优化
        if (Schema::hasTable('weekly_settlement_user_summaries')) {
            // 周键和用户ID复合索引（用于查询用户周结算）
            Schema::table('weekly_settlement_user_summaries', function (Blueprint $table) {
                $table->index(['week_key', 'user_id'], 'idx_wsus_week_user');
            });

            // 用户ID索引（用于查询用户所有结算）
            Schema::table('weekly_settlement_user_summaries', function (Blueprint $table) {
                $table->index('user_id', 'idx_wsus_user_id');
            });

            // 创建时间索引（用于时间范围查询）
            Schema::table('weekly_settlement_user_summaries', function (Blueprint $table) {
                $table->index('created_at', 'idx_wsus_created_at');
            });
        }

        // adjustment_batches 表索引优化
        if (Schema::hasTable('adjustment_batches')) {
            // 批次键唯一索引
            Schema::table('adjustment_batches', function (Blueprint $table) {
                $table->unique('batch_key', 'unique_batch_key');
            });

            // 原因类型索引（用于按类型筛选）
            Schema::table('adjustment_batches', function (Blueprint $table) {
                $table->index('reason_type', 'idx_ab_reason_type');
            });

            // 来源类型和来源ID复合索引
            Schema::table('adjustment_batches', function (Blueprint $table) {
                $table->index(['reference_type', 'reference_id'], 'idx_ab_reference');
            });

            // 结算状态索引（用于查询待处理批次）
            Schema::table('adjustment_batches', function (Blueprint $table) {
                $table->index('finalized_at', 'idx_ab_finalized_at');
            });
        }

        // adjustment_entries 表索引优化
        if (Schema::hasTable('adjustment_entries')) {
            // 批次ID索引（用于查询批次的所有条目）
            Schema::table('adjustment_entries', function (Blueprint $table) {
                $table->index('batch_id', 'idx_ae_batch_id');
            });

            // 用户ID索引（用于查询用户的所有调整）
            Schema::table('adjustment_entries', function (Blueprint $table) {
                $table->index('user_id', 'idx_ae_user_id');
            });

            // 资产类型索引（用于按类型筛选）
            Schema::table('adjustment_entries', function (Blueprint $table) {
                $table->index('asset_type', 'idx_ae_asset_type');
            });
        }

        // users 表索引优化（如果存在）
        if (Schema::hasTable('users')) {
            // 推荐人索引（用于查询下级）
            Schema::table('users', function (Blueprint $table) {
                $table->index('ref_by', 'idx_users_ref_by');
            });

            // 安置人索引（用于查询安置链）
            Schema::table('users', function (Blueprint $table) {
                $table->index('pos_id', 'idx_users_pos_id');
            });

            // 激活状态索引（用于查询活跃用户）
            Schema::table('users', function (Blueprint $table) {
                $table->index('status', 'idx_users_status');
            });

            // 职级索引（用于按职级筛选）
            Schema::table('users', function (Blueprint $table) {
                $table->index('rank_level', 'idx_users_rank_level');
            });
        }

        // user_points_logs 表索引优化（如果存在）
        if (Schema::hasTable('user_points_logs')) {
            // 用户ID和来源类型复合索引
            Schema::table('user_points_logs', function (Blueprint $table) {
                $table->index(['user_id', 'source_type'], 'idx_upl_user_source');
            });

            // 来源类型和来源ID复合索引
            Schema::table('user_points_logs', function (Blueprint $table) {
                $table->index(['source_type', 'source_id'], 'idx_upl_source');
            });

            // 调整批次ID索引
            Schema::table('user_points_logs', function (Blueprint $table) {
                $table->index('adjustment_batch_id', 'idx_upl_adjustment_batch');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // pv_ledger 表索引回滚
        if (Schema::hasTable('pv_ledger')) {
            Schema::table('pv_ledger', function (Blueprint $table) {
                $table->dropIndex('idx_pv_user_position');
                $table->dropIndex('idx_pv_source');
                $table->dropIndex('idx_pv_user_trx_type');
                $table->dropIndex('idx_pv_created_at');
                $table->dropIndex('idx_pv_adjustment_batch');
                $table->dropIndex('idx_pv_reversal_of');
            });
        }

        // transactions 表索引回滚
        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex('idx_trx_user_remark');
                $table->dropIndex('idx_trx_source');
                $table->dropIndex('idx_trx_created_at');
                $table->dropIndex('idx_trx_adjustment_batch');
            });
        }

        // weekly_settlements 表索引回滚
        if (Schema::hasTable('weekly_settlements')) {
            Schema::table('weekly_settlements', function (Blueprint $table) {
                $table->dropUnique('unique_week_key');
                $table->dropIndex('idx_ws_start_date');
                $table->dropIndex('idx_ws_finalized_at');
            });
        }

        // weekly_settlement_user_summaries 表索引回滚
        if (Schema::hasTable('weekly_settlement_user_summaries')) {
            Schema::table('weekly_settlement_user_summaries', function (Blueprint $table) {
                $table->dropIndex('idx_wsus_week_user');
                $table->dropIndex('idx_wsus_user_id');
                $table->dropIndex('idx_wsus_created_at');
            });
        }

        // adjustment_batches 表索引回滚
        if (Schema::hasTable('adjustment_batches')) {
            Schema::table('adjustment_batches', function (Blueprint $table) {
                $table->dropUnique('unique_batch_key');
                $table->dropIndex('idx_ab_reason_type');
                $table->dropIndex('idx_ab_reference');
                $table->dropIndex('idx_ab_finalized_at');
            });
        }

        // adjustment_entries 表索引回滚
        if (Schema::hasTable('adjustment_entries')) {
            Schema::table('adjustment_entries', function (Blueprint $table) {
                $table->dropIndex('idx_ae_batch_id');
                $table->dropIndex('idx_ae_user_id');
                $table->dropIndex('idx_ae_asset_type');
            });
        }

        // users 表索引回滚
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('idx_users_ref_by');
                $table->dropIndex('idx_users_pos_id');
                $table->dropIndex('idx_users_status');
                $table->dropIndex('idx_users_rank_level');
            });
        }

        // user_points_logs 表索引回滚
        if (Schema::hasTable('user_points_logs')) {
            Schema::table('user_points_logs', function (Blueprint $table) {
                $table->dropIndex('idx_upl_user_source');
                $table->dropIndex('idx_upl_source');
                $table->dropIndex('idx_upl_adjustment_batch');
            });
        }
    }
};