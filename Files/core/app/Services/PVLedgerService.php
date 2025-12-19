<?php

namespace App\Services;

use App\Models\User;
use App\Models\PvLedger;
use App\Models\UserExtra;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class PVLedgerService
{
    /**
     * 按安置链（Placement）向上累加PV
     */
    public function creditPV(Order $order): array
    {
        $pvAmount = $order->quantity * 3000;
        $user = $order->user;
        $upline = $this->getPlacementChain($user);
        $results = [];

        foreach ($upline as $node) {
            // 幂等插入PV台账（避免重复累加 user_extras）
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

            // 仅在首次入账时冗余左右区余额
            if ($inserted) {
                $userExtra = UserExtra::firstOrCreate(['user_id' => $node['user_id']]);
                if ((int) $node['position'] === 1) {
                    $userExtra->bv_left = ($userExtra->bv_left ?? 0) + $pvAmount;
                } else {
                    $userExtra->bv_right = ($userExtra->bv_right ?? 0) + $pvAmount;
                }
                $userExtra->save();
            }

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
     */
    private function getPlacementChain(User $user): array
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
     * 获取用户当前PV余额
     */
    public function getUserPVBalance(int $userId): array
    {
        $userExtra = UserExtra::where('user_id', $userId)->first();
        
        return [
            'left_pv' => $userExtra->bv_left ?? 0,
            'right_pv' => $userExtra->bv_right ?? 0,
            'total_pv' => ($userExtra->bv_left ?? 0) + ($userExtra->bv_right ?? 0)
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
            ->where('source_type', 'order')
            ->whereBetween('created_at', [$start, $end])
            ->where('trx_type', '+')
            ->sum('amount');
            
        $rightPV = PvLedger::where('user_id', $userId)
            ->where('position', 2)
            ->where('source_type', 'order')
            ->whereBetween('created_at', [$start, $end])
            ->where('trx_type', '+')
            ->sum('amount');
            
        return min($leftPV, $rightPV);
    }

    /**
        * 用户端PV汇总
        */
    public function getUserPVSummary(int $userId): array
    {
        $balance = $this->getUserPVBalance($userId);
        $currentWeek = now()->format('o-\WW');
        [$start, $end] = $this->getWeekDateRange($currentWeek);

        $leftWeek = PvLedger::where('user_id', $userId)
            ->where('position', 1)
            ->where('source_type', 'order')
            ->whereBetween('created_at', [$start, $end])
            ->where('trx_type', '+')
            ->sum('amount');

        $rightWeek = PvLedger::where('user_id', $userId)
            ->where('position', 2)
            ->where('source_type', 'order')
            ->whereBetween('created_at', [$start, $end])
            ->where('trx_type', '+')
            ->sum('amount');

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
        if (preg_match('/^(\\d{4})-W(\\d{2})$/', $weekKey, $matches)) {
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
}
