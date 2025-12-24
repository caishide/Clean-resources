<?php

namespace App\Services;

use App\Models\AdjustmentBatch;
use App\Models\AdjustmentEntry;
use App\Models\Order;
use App\Models\PvLedger;
use App\Models\Transaction;
use App\Models\UserPointsLog;
use App\Models\UserExtra;
use App\Models\UserAsset;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class AdjustmentService
{
    /**
     * 创建退款调整批次
     * 
     * @param Order $order 订单
     * @param string $reason 退款原因
     * @return AdjustmentBatch 调整批次
     */
    public function createRefundAdjustment(Order $order, string $reason): AdjustmentBatch
    {
        $isBeforeFinalize = $this->isBeforeFinalize($order);

        $batch = AdjustmentBatch::create([
            'batch_key' => $this->generateBatchKey(),
            'reason_type' => $isBeforeFinalize ? 'refund_before_finalize' : 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => $order->trx,
            'snapshot' => json_encode(['order_id' => $order->id, 'reason' => $reason]),
        ]);

        // Finalize 前立即冲正；Finalize 后交由人工审核时调用 finalizeAdjustmentBatch
        if ($isBeforeFinalize) {
            $this->finalizeAdjustmentBatch($batch->id);
        }

        return $batch;
    }

    /**
     * 执行调整批次
     * 
     * @param int $batchId 批次ID
     */
    public function finalizeAdjustmentBatch(int $batchId, ?int $adminId = null): void
    {
        $batch = AdjustmentBatch::findOrFail($batchId);

        DB::transaction(function () use ($batch, $adminId) {
            // 生成负向流水
            // 1. PV台账负向
            $this->reversePVEntries($batch);
            
            // 2. 奖金负向（直推奖、层碰奖、对碰奖、管理奖）
            $this->reverseBonusTransactions($batch);
            
            // 3. 莲子负向
            $this->reversePointsEntries($batch);

            $batch->finalized_at = now();
            if ($adminId) {
                $batch->finalized_by = $adminId;
            }
            $batch->save();

            // 记录审计日志（非关键，失败不中断）
            try {
                AuditLog::create([
                    'admin_id' => $adminId,
                    'action_type' => 'adjustment_finalize',
                    'entity_type' => 'adjustment_batch',
                    'entity_id' => $batch->id,
                    'meta' => ['batch_key' => $batch->batch_key],
                ]);
            } catch (\Exception $e) {
                // 审计日志失败不影响核心逻辑
            }
        });
    }

    /**
     * 冲正PV台账
     */
    private function reversePVEntries(AdjustmentBatch $batch): void
    {
        $originalPVEntries = PvLedger::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->get();
            
        foreach ($originalPVEntries as $entry) {
            $negativeAmount = -abs($entry->amount);

            // 创建负向PV记录
            PvLedger::create([
                'user_id' => $entry->user_id,
                'from_user_id' => $entry->from_user_id,
                'position' => $entry->position,
                'level' => $entry->level,
                'amount' => $negativeAmount,
                'trx_type' => '-',
                'source_type' => 'adjustment',
                'source_id' => $batch->batch_key,
                'adjustment_batch_id' => $batch->id,
                'reversal_of_id' => $entry->id
            ]);

            // 同步左右区余额和总量
            $userExtra = UserExtra::firstOrCreate(['user_id' => $entry->user_id]);
            $oldLeft = $userExtra->bv_left ?? 0;
            $oldRight = $userExtra->bv_right ?? 0;
            $hasTotalBv = isset($userExtra->getAttributes()['total_bv']);

            if ((int) $entry->position === 1) {
                $userExtra->bv_left = max(0, $oldLeft + $negativeAmount);
            } elseif ((int) $entry->position === 2) {
                $userExtra->bv_right = max(0, $oldRight + $negativeAmount);
            }

            // 同步更新总 PV（如果字段存在）
            if ($hasTotalBv) {
                $newLeft = $userExtra->bv_left;
                $newRight = $userExtra->bv_right;
                $userExtra->total_bv = $newLeft + $newRight;
            }

            $userExtra->save();
            
            // 记录调整条目
            AdjustmentEntry::create([
                'batch_id' => $batch->id,
                'asset_type' => 'pv',
                'user_id' => $entry->user_id,
                'amount' => $negativeAmount,
                'reversal_of_id' => $entry->id
            ]);
        }
    }

    /**
     * 冲正奖金交易
     * 
     * 回滚所有与该订单相关的奖金：直推奖、层碰奖、对碰奖、管理奖
     */
    private function reverseBonusTransactions(AdjustmentBatch $batch): void
    {
        // 1. 回滚直推奖和层碰奖（直接关联订单）
        $originalTransactions = Transaction::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->whereIn('remark', ['direct_bonus', 'level_pair_bonus'])
            ->get();

        foreach ($originalTransactions as $trx) {
            $this->createReversalTransaction($batch, $trx);
        }

        // 2. 回滚对碰奖和管理奖（关联周结算）
        // 找到该订单产生的 PV 记录，获知所有受益人
        $pvEntries = PvLedger::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->where('trx_type', '+')
            ->get();
        
        $beneficiaryUserIds = $pvEntries->pluck('user_id')->unique()->toArray();
        
        if (empty($beneficiaryUserIds)) {
            return;
        }

        // 获取相关周结算（可能有多个）
        $weekKeys = $this->getWeekKeysForBeneficiaries($beneficiaryUserIds, $batch->reference_id);
        
        foreach ($weekKeys as $weekKey) {
            // 找到该周结算中所有受益人的对碰奖和管理奖
            $settlementTransactions = Transaction::where('source_type', 'weekly_settlement')
                ->where('source_id', $weekKey)
                ->whereIn('user_id', $beneficiaryUserIds)
                ->whereIn('remark', ['pair_bonus', 'matching_bonus'])
                ->get();
                
            foreach ($settlementTransactions as $trx) {
                $this->createReversalTransaction($batch, $trx);
            }
        }
    }

    /**
     * 创建负向交易记录
     */
    private function createReversalTransaction(AdjustmentBatch $batch, Transaction $originalTrx): void
    {
        $user = User::find($originalTrx->user_id);
        $negativeAmount = -abs($originalTrx->amount);
        $postBalance = $user ? ($user->balance + $negativeAmount) : $negativeAmount;

        if ($user) {
            $user->balance = $postBalance;
            $user->save();
        }

        // 创建负向交易
        Transaction::create([
            'user_id' => $originalTrx->user_id,
            'trx_type' => '-',
            'amount' => $negativeAmount,
            'remark' => $originalTrx->remark . '_reversal',
            'source_type' => 'adjustment',
            'source_id' => $batch->batch_key,
            'adjustment_batch_id' => $batch->id,
            'reversal_of_id' => $originalTrx->id,
            'details' => json_encode([
                'adjustment_batch_id' => $batch->id,
                'original_trx_id' => $originalTrx->id,
                'original_week_key' => $originalTrx->source_id
            ]),
            'post_balance' => $postBalance,
            'charge' => 0,
        ]);
        
        // 记录调整条目
        AdjustmentEntry::create([
            'batch_id' => $batch->id,
            'asset_type' => 'wallet',
            'user_id' => $originalTrx->user_id,
            'amount' => $negativeAmount,
            'reversal_of_id' => $originalTrx->id
        ]);
    }

    /**
     * 获取受益人相关的周结算键
     * 通过 PV 记录精确关联订单和周结算
     */
    private function getWeekKeysForBeneficiaries(array $userIds, string $orderTrx): array
    {
        // 方法1：通过 PvLedger 关联表，找到每个受益人该订单 PV 产生的周结算
        // pv_ledger 表没有 week_key 字段，改用结算汇总表查询

        // 计算该订单产生的 PV 总量
        $orderPVAmount = DB::table('pv_ledger')
            ->where('source_type', 'order')
            ->where('source_id', $orderTrx)
            ->sum('amount');

        if ($orderPVAmount <= 0) {
            return [];
        }

        // 在周结算汇总中寻找该用户在该订单日期之后的结算记录
        $orderDatePrefix = substr($orderTrx, 0, 10);

        $weekKeys = DB::table('weekly_settlement_user_summaries')
            ->whereIn('user_id', $userIds)
            ->where(function ($q) {
                $q->where('pair_paid', '>', 0)
                  ->orWhere('matching_paid', '>', 0);
            })
            ->where('created_at', '>=', $orderDatePrefix) // 只回滚订单之后的结算
            ->pluck('week_key')
            ->unique()
            ->toArray();

        return $weekKeys;
    }

    /**
     * 冲正莲子积分
     */
    private function reversePointsEntries(AdjustmentBatch $batch): void
    {
        $originalPointsEntries = UserPointsLog::where(function ($q) {
                $q->where('source_type', 'PURCHASE')
                  ->orWhere('source_type', 'DIRECT');
            })
            ->where('source_id', $batch->reference_id)
            ->get();
            
        foreach ($originalPointsEntries as $entry) {
            $negativePoints = -abs($entry->points);

            // 创建负向积分记录
            UserPointsLog::create([
                'user_id' => $entry->user_id,
                'source_type' => $entry->source_type . '_REVERSAL',
                'source_id' => $batch->batch_key,
                'points' => $negativePoints,
                'description' => $entry->description . '（冲正）',
                'adjustment_batch_id' => $batch->id,
                'reversal_of_id' => $entry->id,
            ]);
            
            // 记录调整条目
            AdjustmentEntry::create([
                'batch_id' => $batch->id,
                'asset_type' => 'points',
                'user_id' => $entry->user_id,
                'amount' => $negativePoints,
                'reversal_of_id' => $entry->id
            ]);
            
            // 更新用户莲子余额
            $userExtra = UserExtra::firstOrCreate(['user_id' => $entry->user_id]);
            $userExtra->points = max(0, ($userExtra->points ?? 0) + $negativePoints);
            $userExtra->save();

            $asset = UserAsset::firstOrCreate(['user_id' => $entry->user_id]);
            $asset->points = max(0, ($asset->points ?? 0) + $negativePoints);
            $asset->save();
        }
    }

    /**
     * 判断是否在Finalize前
     */
    private function isBeforeFinalize(Order $order): bool
    {
        $weekKey = $order->created_at ? $order->created_at->format('o-\\WW') : now()->format('o-\\WW');
        $settlement = DB::table('weekly_settlements')->where('week_key', $weekKey)->first();

        // 未结算或未finalize 即认为在结算前
        if (!$settlement || empty($settlement->finalized_at)) {
            return true;
        }

        return false;
    }

    /**
     * 生成批次号
     */
    public function generateBatchKey(): string
    {
        return 'ADJ-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
    }

    /**
     * 获取调整批次列表
     */
    public function getAdjustmentBatches(array $filters = []): array
    {
        $query = AdjustmentBatch::query();
        
        if (!empty($filters['reason_type'])) {
            $query->where('reason_type', $filters['reason_type']);
        }
        
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'pending') {
                $query->whereNull('finalized_at');
            } elseif ($filters['status'] === 'finalized') {
                $query->whereNotNull('finalized_at');
            }
        }
        
        return $query->orderBy('created_at', 'desc')->paginate(20)->toArray();
    }

    /**
     * 获取调整批次详情
     */
    public function getAdjustmentBatchDetails(int $batchId): array
    {
        $batch = AdjustmentBatch::findOrFail($batchId);
        $entries = AdjustmentEntry::where('batch_id', $batchId)->get();
        
        return [
            'batch' => $batch,
            'entries' => $entries
        ];
    }
}
