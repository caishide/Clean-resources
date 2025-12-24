<?php

namespace App\Services;

use App\Models\User;
use App\Models\PvLedger;
use App\Models\UserExtra;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class PVLedgerService
{
    /**
     * 按安置链（Placement）向上累加PV - 纯台账方案
     * 
     * 只写入 pv_ledger 台账，不更新 user_extras.bv_left/bv_right
     * 结算时从台账聚合计算弱区 PV
     */
    public function creditPV(Order $order): array
    {
        $pvAmount = $order->quantity * 3000;
        $user = $order->user;
        $upline = $this->getPlacementChain($user);
        $results = [];

        foreach ($upline as $node) {
            // 幂等插入PV台账
            $inserted = DB::table('pv_ledger')->insertOrIgnore([
                [
                    'user_id' => $node['user_id'],
                    'from_user_id' => $user->id,
                    'position' => $node['position'],
                    'level' => $node['level'],
                    'amount' => $pvAmount,
                    'trx_type' => '+',
                    'source_type' => 'order',
                    'source_id' => $order->trx,
                    'adjustment_batch_id' => null,
                    'reversal_of_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $results[] = [
                'user_id' => $node['user_id'],
                'position' => $node['position'],
                'level' => $node['level'],
                'amount' => $pvAmount,
                'inserted' => (bool) $inserted,
            ];
        }

        return $results;
    }

    /**
     * 订单发货时沿安置链累加PV
     * 
     * @param Order $order 订单
     * @return array 累加结果
     */
    public function creditPVFromOrder(Order $order): array
    {
        return $this->creditPV($order);
    }

    /**
     * 获取安置链（无限层，直到根或无pos_id）
     *
     * 优化版本：使用缓存避免 N+1 查询问题
     */
    private function getPlacementChain(User $user): array
    {
        // 使用缓存存储安置链，缓存 24 小时
        return Cache::remember(
            "placement_chain:{$user->id}",
            now()->addHours(24),
            function () use ($user) {
                return $this->calculatePlacementChain($user);
            }
        );
    }

    /**
     * 计算安置链（实际执行查询的方法）
     *
     * @param User $user 用户
     * @return array 安置链
     */
    private function calculatePlacementChain(User $user): array
    {
        $chain = [];
        $current = $user;
        $level = 0;

        while ($current && $current->pos_id) {
            $parent = User::find($current->pos_id);
            if (!$parent) {
                break;
            }
            $level++;
            $position = $current->position ?: 1; // 默认左区

            $chain[] = [
                'user_id' => $parent->id,
                'user' => $parent,
                'position' => $position,
                'level' => $level,
            ];

            $current = $parent;
        }

        return $chain;
    }

    /**
     * 清除用户安置链缓存
     *
     * 当用户的安置关系发生变化时调用此方法
     *
     * @param int $userId 用户ID
     * @return void
     */
    public function clearPlacementChainCache(int $userId): void
    {
        Cache::forget("placement_chain:{$userId}");
    }

    /**
     * 获取用户当前PV余额
     */
    public function getUserPVBalance(int $userId, bool $includeCarry = true): array
    {
        $leftQuery = PvLedger::where('user_id', $userId)
            ->where('position', 1);
        $rightQuery = PvLedger::where('user_id', $userId)
            ->where('position', 2);

        if (!$includeCarry) {
            $leftQuery->whereNotIn('source_type', $this->getCarryFlashSourceTypes());
            $rightQuery->whereNotIn('source_type', $this->getCarryFlashSourceTypes());
        }

        $leftPv = $leftQuery->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
        $rightPv = $rightQuery->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));

        return [
            'left_pv' => $leftPv,
            'right_pv' => $rightPv,
            'total_pv' => $leftPv + $rightPv,
        ];
    }

    /**
     * 获取本周新增弱区PV（用于TEAM莲子计算）
     */
    public function getWeeklyNewWeakPV(int $userId, string $weekKey): float
    {
        list($start, $end) = $this->getWeekDateRange($weekKey);
        
        $leftPV = PvLedger::where('user_id', $userId)
            ->where('position', 1)
            ->whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
            
        $rightPV = PvLedger::where('user_id', $userId)
            ->where('position', 2)
            ->whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
            
        return min($leftPV, $rightPV);
    }

    /**
     * 用户端PV汇总
     */
    public function getUserPVSummary(int $userId, bool $includeCarry = true): array
    {
        $balance = $this->getUserPVBalance($userId, $includeCarry);
        $currentWeek = now()->format('o-\WW');
        [$start, $end] = $this->getWeekDateRange($currentWeek);

        $leftWeek = PvLedger::where('user_id', $userId)
            ->where('position', 1)
            ->whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));

        $rightWeek = PvLedger::where('user_id', $userId)
            ->where('position', 2)
            ->whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));

        return [
            'left_pv' => $balance['left_pv'],
            'right_pv' => $balance['right_pv'],
            'this_week_left' => $leftWeek,
            'this_week_right' => $rightWeek,
        ];
    }

    /**
     * 获取周日期范围
     */
    private function getWeekDateRange(string $weekKey): array
    {
        // 解析 week_key 格式，如 '2025-W51'
        if (preg_match('/^(\d{4})-W(\d{2})$/', $weekKey, $matches)) {
            $year = $matches[1];
            $week = $matches[2];
            
            $date = new \DateTime();
            $date->setISODate($year, $week);
            $start = clone $date;
            $start->setTime(0, 0, 0);
            
            $end = clone $date;
            $end->modify('+6 days');
            $end->setTime(23, 59, 59);
            
            return [$start, $end];
        }
        
        // 默认返回当前周
        return [now()->startOfWeek(), now()->endOfWeek()];
    }

    /**
      * 周结算时扣减PV
      */
    public function deductPVForWeeklySettlement(int $userId, float $deductPV, string $weekKey): void
    {
        PvLedger::create([
            'user_id' => $userId,
            'from_user_id' => null,
            'position' => 0,
            'level' => 0,
            'amount' => $deductPV,
            'trx_type' => '-',
            'source_type' => 'weekly_settlement',
            'source_id' => $weekKey
        ]);

        // 更新用户PV余额
        $userExtra = UserExtra::where('user_id', $userId)->first();
        if ($userExtra) {
            // 假设按比例扣减左右区PV
            $totalPV = $userExtra->bv_left + $userExtra->bv_right;
            if ($totalPV > 0) {
                $leftRatio = $userExtra->bv_left / $totalPV;
                $rightRatio = $userExtra->bv_right / $totalPV;
                
                $userExtra->bv_left = max(0, $userExtra->bv_left - ($deductPV * $leftRatio));
                $userExtra->bv_right = max(0, $userExtra->bv_right - ($deductPV * $rightRatio));
                $userExtra->save();
            }
        }
    }

    /**
     * 结转时扣减PV（纯台账方案）
     * 
     * 生成 PV 调整记录，用于扣除或冲回 PV（幂等）
     * 
     * @param int $userId 用户ID
     * @param int $position 区位置（1=左区，2=右区）
     * @param float $amount 扣减金额
     * @param string $weekKey 周键
     * @param string $remark 备注
     * @param string $sourceSource 来源类型
     * @param string $trxType 交易类型（+ 或 -）
     * @return bool 是否插入成功
     */
    public function creditCarryFlash(
        int $userId,
        int $position,
        float $amount,
        string $weekKey,
        string $remark,
        string $sourceSource = 'carry_flash',
        string $trxType = '-'
    ): bool {
        $inserted = DB::table('pv_ledger')->insertOrIgnore([
            [
            'user_id' => $userId,
            'from_user_id' => null,
            'position' => $position,
            'level' => 0,
            'amount' => abs($amount),
            'trx_type' => $trxType,
            'source_type' => $sourceSource,
            'source_id' => $weekKey,
            'details' => $remark,
            'created_at' => now(),
            'updated_at' => now()
            ],
        ]);
        return (bool) $inserted;
    }

    private function getCarryFlashSourceTypes(): array
    {
        return [
            'carry_flash',
            'carry_flash_deduct_paid',
            'carry_flash_deduct_weak',
            'carry_flash_flush_all',
            'carry_flash_cap_excess',
        ];
    }
}
