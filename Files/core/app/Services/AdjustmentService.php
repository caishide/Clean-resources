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
use App\Models\PendingBonus;
use App\Models\UserLevelHit;
use App\Models\WeeklySettlementUserSummary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdjustmentService
{
    /**
     * 创建退款调整批次
     *
     * @param Order $order 订单
     * @param string $reason 退款原因
     * @param int|null $operatorId 操作人ID（管理员）
     * @return AdjustmentBatch 调整批次
     */
    public function createRefundAdjustment(Order $order, string $reason, ?int $operatorId = null): AdjustmentBatch
    {
        $isBeforeFinalize = $this->isBeforeFinalize($order);

        $batch = AdjustmentBatch::create([
            'batch_key' => $this->generateBatchKey(),
            'reason_type' => $isBeforeFinalize ? 'refund_before_finalize' : 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => $order->trx,
            'snapshot' => json_encode([
                'order_id' => $order->id,
                'order_trx' => $order->trx,
                'reason' => $reason,
                'operator_id' => $operatorId,
                'created_at' => now()->toIso8601String()
            ], JSON_UNESCAPED_UNICODE),
        ]);

        // Finalize 前立即冲正；Finalize 后交由人工审核
        if ($isBeforeFinalize) {
            $this->finalizeAdjustmentBatch($batch->id, $operatorId);
        }

        return $batch;
    }

    /**
     * 执行调整批次终态化
     *
     * @param int $batchId 批次ID
     * @param int|null $adminId 管理员ID
     * @param bool $force 是否强制终态化（跳过部分验证）
     * @return array 冲正结果
     */
    public function finalizeAdjustmentBatch(int $batchId, ?int $adminId = null, bool $force = false): array
    {
        $batch = AdjustmentBatch::findOrFail($batchId);

        // 检查是否已终态化
        if ($batch->finalized_at && !$force) {
            throw new \RuntimeException("调整批次 {$batch->batch_key} 已终态化");
        }

        $result = [
            'batch_id' => $batchId,
            'batch_key' => $batch->batch_key,
            'pv_entries_reversed' => 0,
            'bonus_transactions_reversed' => 0,
            'pending_bonuses_reversed' => 0,
            'points_entries_reversed' => 0,
            'team_points_reversed' => 0,
            'level_hits_reverted' => 0,
        ];

        DB::transaction(function () use ($batch, $adminId, &$result, $force) {
            // 1. PV台账负向冲正
            $result['pv_entries_reversed'] = $this->reversePVEntries($batch);

            // 2. 奖金交易负向冲正（已发放的直推奖、层碰奖、对碰奖、管理奖）
            $result['bonus_transactions_reversed'] = $this->reverseBonusTransactions($batch);

            // 3. 待处理奖金冲正（未激活用户的应计预留）
            $result['pending_bonuses_reversed'] = $this->reversePendingBonuses($batch);

            // 4. 层碰记录回滚（user_level_hits）
            $result['level_hits_reverted'] = $this->revertLevelPairHits($batch);

            // 5. 莲子积分冲正（PURCHASE/DIRECT）
            $result['points_entries_reversed'] = $this->reversePointsEntries($batch);

            // 6. TEAM莲子冲正（关联周结算）
            $result['team_points_reversed'] = $this->reverseTeamPoints($batch);

            // 更新批次状态
            $batch->finalized_at = now();
            if ($adminId) {
                $batch->finalized_by = $adminId;
            }
            $batch->save();

            // 记录审计日志
            $this->createAuditLog($adminId, $batch, $result);
        });

        Log::info('Adjustment batch finalized', [
            'batch_id' => $batchId,
            'batch_key' => $batch->batch_key,
            'result' => $result
        ]);

        return $result;
    }

    /**
     * 冲正PV台账
     *
     * @param AdjustmentBatch $batch 调整批次
     * @return int 冲正记录数
     */
    private function reversePVEntries(AdjustmentBatch $batch): int
    {
        $originalPVEntries = PvLedger::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->get();

        $count = 0;
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

            // 同步更新用户 PV 余额（从 pv_ledger 聚合计算，不直接操作 user_extras）
            // 注意：PV 余额从 pv_ledger 实时聚合，不维护冗余字段

            // 记录调整条目
            AdjustmentEntry::create([
                'batch_id' => $batch->id,
                'asset_type' => 'pv',
                'user_id' => $entry->user_id,
                'amount' => $negativeAmount,
                'reversal_of_id' => $entry->id,
                'position' => $entry->position,
                'description' => "PV冲正-订单{$batch->reference_id}"
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * 冲正奖金交易
     *
     * 回滚所有与该订单相关的奖金：直推奖、层碰奖、对碰奖、管理奖
     *
     * @param AdjustmentBatch $batch 调整批次
     * @return int 冲正记录数
     */
    private function reverseBonusTransactions(AdjustmentBatch $batch): int
    {
        $count = 0;

        // 1. 回滚直推奖和层碰奖（直接关联订单）
        $originalTransactions = Transaction::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->whereIn('remark', ['direct_bonus', 'level_pair_bonus'])
            ->get();

        foreach ($originalTransactions as $trx) {
            $this->createReversalTransaction($batch, $trx);
            $count++;
        }

        // 2. 回滚对碰奖和管理奖（关联周结算）
        $pvEntries = PvLedger::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->where('trx_type', '+')
            ->get();

        $beneficiaryUserIds = $pvEntries->pluck('user_id')->unique()->toArray();

        if (empty($beneficiaryUserIds)) {
            return $count;
        }

        // 获取相关周结算
        $weekKeys = $this->getWeekKeysForBeneficiaries($beneficiaryUserIds, $batch->reference_id);

        foreach ($weekKeys as $weekKey) {
            $settlementTransactions = Transaction::where('source_type', 'weekly_settlement')
                ->where('source_id', $weekKey)
                ->whereIn('user_id', $beneficiaryUserIds)
                ->whereIn('remark', ['pair_bonus', 'matching_bonus'])
                ->get();

            foreach ($settlementTransactions as $trx) {
                $this->createReversalTransaction($batch, $trx);
                $count++;
            }
        }

        return $count;
    }

    /**
     * 创建负向交易记录
     *
     * @param AdjustmentBatch $batch 调整批次
     * @param Transaction $originalTrx 原始交易
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
                'original_week_key' => $originalTrx->source_id,
                'original_amount' => $originalTrx->amount,
                'reversal_reason' => 'refund'
            ], JSON_UNESCAPED_UNICODE),
            'post_balance' => $postBalance,
            'charge' => 0,
        ]);

        // 记录调整条目
        AdjustmentEntry::create([
            'batch_id' => $batch->id,
            'asset_type' => 'wallet',
            'user_id' => $originalTrx->user_id,
            'amount' => $negativeAmount,
            'reversal_of_id' => $originalTrx->id,
            'description' => "奖金冲正-{$originalTrx->remark}"
        ]);
    }

    /**
     * 冲正待处理奖金
     *
     * 冲正因未激活而进入待处理的直推奖、层碰奖、对碰奖、管理奖
     *
     * @param AdjustmentBatch $batch 调整批次
     * @return int 冲正记录数
     */
    private function reversePendingBonuses(AdjustmentBatch $batch): int
    {
        $count = 0;

        // 查找与该订单相关的待处理奖金
        $pendingBonuses = PendingBonus::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->where('status', 'pending')
            ->get();

        foreach ($pendingBonuses as $bonus) {
            // 更新状态为已冲正
            $bonus->status = 'reversed';
            $bonus->reversal_batch_id = $batch->id;
            $bonus->save();

            // 记录调整条目
            AdjustmentEntry::create([
                'batch_id' => $batch->id,
                'asset_type' => 'pending_bonus',
                'user_id' => $bonus->recipient_id,
                'amount' => -$bonus->amount,
                'reversal_of_id' => $bonus->id,
                'description' => "待处理奖金冲正-{$bonus->bonus_type}"
            ]);

            $count++;
        }

        // 同时冲正与周结算相关的待处理奖金（对碰奖、管理奖）
        $pvEntries = PvLedger::where('source_type', 'order')
            ->where('source_id', $batch->reference_id)
            ->where('trx_type', '+')
            ->get();

        $beneficiaryUserIds = $pvEntries->pluck('user_id')->unique()->toArray();

        if (!empty($beneficiaryUserIds)) {
            $weekKeys = $this->getWeekKeysForBeneficiaries($beneficiaryUserIds, $batch->reference_id);

            foreach ($weekKeys as $weekKey) {
                $pendingWeeklyBonuses = PendingBonus::where('source_type', 'weekly_settlement')
                    ->where('source_id', $weekKey)
                    ->whereIn('recipient_id', $beneficiaryUserIds)
                    ->where('status', 'pending')
                    ->get();

                foreach ($pendingWeeklyBonuses as $bonus) {
                    $bonus->status = 'reversed';
                    $bonus->reversal_batch_id = $batch->id;
                    $bonus->save();

                    AdjustmentEntry::create([
                        'batch_id' => $batch->id,
                        'asset_type' => 'pending_bonus',
                        'user_id' => $bonus->recipient_id,
                        'amount' => -$bonus->amount,
                        'reversal_of_id' => $bonus->id,
                        'description' => "待处理奖金冲正-{$bonus->bonus_type}(周结算)"
                    ]);

                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * 回滚层碰记录
     *
     * 恢复 user_level_hits 表中的点亮状态和奖励标记
     *
     * @param AdjustmentBatch $batch 调整批次
     * @return int 回滚记录数
     */
    private function revertLevelPairHits(AdjustmentBatch $batch): int
    {
        $count = 0;

        // 查找与该订单相关的层碰记录
        $levelHits = UserLevelHit::where('order_trx', $batch->reference_id)
            ->where('rewarded', 1)
            ->get();

        foreach ($levelHits as $hit) {
            // 检查该层级是否还有其他已奖励的订单
            $otherRewardedCount = UserLevelHit::where('user_id', $hit->user_id)
                ->where('level', $hit->level)
                ->where('id', '!=', $hit->id)
                ->where('rewarded', 1)
                ->count();

            if ($otherRewardedCount === 0) {
                // 没有其他已奖励订单，完全回滚
                $hit->rewarded = 0;
                $hit->bonus_amount = 0;
                $hit->save();
            } else {
                // 有其他已奖励订单，仅减少金额
                $hit->amount = max(0, $hit->amount - ($hit->amount / $otherRewardedCount));
                $hit->save();
            }

            AdjustmentEntry::create([
                'batch_id' => $batch->id,
                'asset_type' => 'level_hit',
                'user_id' => $hit->user_id,
                'amount' => 0,
                'reversal_of_id' => $hit->id,
                'description' => "层碰记录回滚-Level{$hit->level}"
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * 冲正莲子积分（PURCHASE/DIRECT）
     *
     * @param AdjustmentBatch $batch 调整批次
     * @return int 冲正记录数
     */
    private function reversePointsEntries(AdjustmentBatch $batch): int
    {
        $originalPointsEntries = UserPointsLog::whereIn('source_type', ['PURCHASE', 'DIRECT'])
            ->where('source_id', $batch->reference_id)
            ->get();

        $count = 0;
        foreach ($originalPointsEntries as $entry) {
            $negativePoints = -abs($entry->points);

            // 创建负向积分记录
            UserPointsLog::create([
                'user_id' => $entry->user_id,
                'source_type' => $entry->source_type . '_REVERSAL',
                'source_id' => $batch->batch_key,
                'points' => $negativePoints,
                'description' => $entry->description . '（退款冲正）',
                'adjustment_batch_id' => $batch->id,
                'reversal_of_id' => $entry->id,
            ]);

            // 更新用户莲子余额
            $asset = UserAsset::firstOrCreate(['user_id' => $entry->user_id]);
            $asset->points = max(0, ($asset->points ?? 0) + $negativePoints);
            $asset->save();

            // 记录调整条目
            AdjustmentEntry::create([
                'batch_id' => $batch->id,
                'asset_type' => 'points',
                'user_id' => $entry->user_id,
                'amount' => $negativePoints,
                'reversal_of_id' => $entry->id,
                'description' => "莲子冲正-{$entry->source_type}"
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * 冲正TEAM莲子（关联周结算）
     *
     * TEAM莲子按周结算产出，退款时需要找到对应的周结算并冲正
     *
     * @param AdjustmentBatch $batch 调整批次
     * @return int 冲正记录数
     */
    private function reverseTeamPoints(AdjustmentBatch $batch): int
    {
        $count = 0;

        // 获取订单日期
        $orderTrx = $batch->reference_id;
        $orderDatePrefix = substr($orderTrx, 0, 10);

        // 查找该订单日期之后的TEAM莲子记录
        $teamPointsEntries = UserPointsLog::where('source_type', 'TEAM')
            ->where('created_at', '>=', $orderDatePrefix)
            ->where('description', 'LIKE', '%' . substr($orderTrx, -8) . '%') // 尝试匹配订单特征
            ->get();

        // 由于TEAM莲子按周产出，采用更精确的匹配方式：
        // 计算该订单产生的PV总量，然后在周结算汇总中找到对应的TEAM产出
        $orderPVAmount = PvLedger::where('source_type', 'order')
            ->where('source_id', $orderTrx)
            ->sum('amount');

        if ($orderPVAmount <= 0) {
            return $count;
        }

        // 获取所有相关用户在该订单日期之后的周结算
        $pvEntries = PvLedger::where('source_type', 'order')
            ->where('source_id', $orderTrx)
            ->where('trx_type', '+')
            ->get();

        $beneficiaryUserIds = $pvEntries->pluck('user_id')->unique()->toArray();

        if (empty($beneficiaryUserIds)) {
            return $count;
        }

        $weekKeys = DB::table('weekly_settlement_user_summaries')
            ->whereIn('user_id', $beneficiaryUserIds)
            ->where('created_at', '>=', $orderDatePrefix)
            ->pluck('week_key')
            ->unique()
            ->toArray();

        foreach ($weekKeys as $weekKey) {
            $teamPointsEntries = UserPointsLog::where('source_type', 'TEAM')
                ->where('source_id', $weekKey)
                ->whereIn('user_id', $beneficiaryUserIds)
                ->get();

            foreach ($teamPointsEntries as $entry) {
                $negativePoints = -abs($entry->points);

                // 创建负向TEAM莲子记录
                UserPointsLog::create([
                    'user_id' => $entry->user_id,
                    'source_type' => 'TEAM_REVERSAL',
                    'source_id' => $batch->batch_key,
                    'points' => $negativePoints,
                    'description' => $entry->description . '（退款冲正）',
                    'adjustment_batch_id' => $batch->id,
                    'reversal_of_id' => $entry->id,
                ]);

                // 更新用户莲子余额
                $asset = UserAsset::firstOrCreate(['user_id' => $entry->user_id]);
                $asset->points = max(0, ($asset->points ?? 0) + $negativePoints);
                $asset->save();

                // 记录调整条目
                AdjustmentEntry::create([
                    'batch_id' => $batch->id,
                    'asset_type' => 'team_points',
                    'user_id' => $entry->user_id,
                    'amount' => $negativePoints,
                    'reversal_of_id' => $entry->id,
                    'description' => "TEAM莲子冲正-{$weekKey}"
                ]);

                $count++;
            }
        }

        return $count;
    }

    /**
     * 获取受益人相关的周结算键
     * 通过 PV 记录精确关联订单和周结算
     *
     * @param array $userIds 用户ID数组
     * @param string $orderTrx 订单交易号
     * @return array 周结算键数组
     */
    private function getWeekKeysForBeneficiaries(array $userIds, string $orderTrx): array
    {
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
            ->where('created_at', '>=', $orderDatePrefix)
            ->pluck('week_key')
            ->unique()
            ->toArray();

        return $weekKeys;
    }

    /**
     * 创建审计日志
     *
     * @param int|null $adminId 管理员ID
     * @param AdjustmentBatch $batch 调整批次
     * @param array $result 冲正结果
     */
    private function createAuditLog(?int $adminId, AdjustmentBatch $batch, array $result): void
    {
        try {
            AuditLog::create([
                'admin_id' => $adminId,
                'action_type' => 'adjustment_finalize',
                'entity_type' => 'adjustment_batch',
                'entity_id' => $batch->id,
                'meta' => [
                    'batch_key' => $batch->batch_key,
                    'reason_type' => $batch->reason_type,
                    'reference_id' => $batch->reference_id,
                    'result' => $result,
                    'finalized_at' => now()->toIso8601String()
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to create audit log for adjustment batch', [
                'batch_id' => $batch->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 判断订单是否在周结算终态化之前
     *
     * @param Order $order 订单
     * @return bool true=结算前，false=结算后
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
     * 检查用户是否因退款导致负余额而限制提现
     *
     * @param int $userId 用户ID
     * @return array ['can_withdraw' => bool, 'reason' => string, 'balance' => float]
     */
    public function checkWithdrawalRestriction(int $userId): array
    {
        $user = User::find($userId);
        $balance = $user ? (float) $user->balance : 0;

        // 查找最近的退款冲正记录
        $recentRefund = AdjustmentBatch::where('reason_type', 'refund_after_finalize')
            ->whereHas('entries', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->whereIn('asset_type', ['wallet', 'pending_bonus']);
            })
            ->where('finalized_at', '>=', now()->subDays(30))
            ->orderBy('finalized_at', 'desc')
            ->first();

        return [
            'can_withdraw' => $balance >= 0,
            'reason' => $balance < 0 ? '余额为负，请等待后续收益优先抵扣' : null,
            'balance' => $balance,
            'has_recent_refund' => $recentRefund !== null,
        ];
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
