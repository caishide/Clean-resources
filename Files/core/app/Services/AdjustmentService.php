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
            
            // 2. 奖金负向
            $this->reverseBonusTransactions($batch);
            
            // 3. 莲子负向
            $this->reversePointsEntries($batch);

            $batch->finalized_at = now();
            if ($adminId) {
                $batch->finalized_by = $adminId;
            }
            $batch->save();

            AuditLog::create([
                'admin_id' => $adminId,
                'action_type' => 'adjustment_finalize',
                'entity_type' => 'adjustment_batch',
                'entity_id' => $batch->id,
                'meta' => ['batch_key' => $batch->batch_key],
            ]);
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

            // 同步左右区余额
            $userExtra = UserExtra::firstOrCreate(['user_id' => $entry->user_id]);
            if ((int) $entry->position === 1) {
                $userExtra->bv_left = max(0, ($userExtra->bv_left ?? 0) + $negativeAmount);
            } elseif ((int) $entry->position === 2) {
                $userExtra->bv_right = max(0, ($userExtra->bv_right ?? 0) + $negativeAmount);
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
     */
    private function reverseBonusTransactions(AdjustmentBatch $batch): void
    {
        $originalTransactions = Transaction::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->whereIn('remark', ['direct_bonus', 'level_pair_bonus'])
            ->get();
            
        foreach ($originalTransactions as $trx) {
            $user = User::find($trx->user_id);
            $negativeAmount = -abs($trx->amount);
            $postBalance = $user ? ($user->balance + $negativeAmount) : $negativeAmount;
            if ($user) {
                $user->balance = $postBalance;
                $user->save();
            }

            // 创建负向交易
            Transaction::create([
                'user_id' => $trx->user_id,
                'trx_type' => '-',
                'amount' => abs($trx->amount),
                'remark' => $trx->remark . '_reversal',
                'source_type' => 'adjustment',
                'source_id' => $batch->batch_key,
                'adjustment_batch_id' => $batch->id,
                'reversal_of_id' => $trx->id,
                'details' => json_encode(['adjustment_batch_id' => $batch->id, 'original_trx_id' => $trx->id]),
                'post_balance' => $postBalance,
                'charge' => 0,
            ]);
            
            // 记录调整条目
            AdjustmentEntry::create([
                'batch_id' => $batch->id,
                'asset_type' => 'wallet',
                'user_id' => $trx->user_id,
                'amount' => $negativeAmount,
                'reversal_of_id' => $trx->id
            ]);
        }
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
    private function generateBatchKey(): string
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
