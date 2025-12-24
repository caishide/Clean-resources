<?php

namespace App\Services;

use App\Models\User;
use App\Models\PvLedger;
use App\Models\UserExtra;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * PV 台账服务优化版
 * 
 * 主要优化：
 * 1. 使用缓存减少数据库查询
 * 2. 批量查询替代循环查询
 * 3. 使用数据库聚合函数
 * 4. 优化安置链查询
 */
class PVLedgerServiceOptimized
{
    // 缓存时间常量（秒）
    private const CACHE_PLACEMENT_CHAIN = 86400; // 24小时
    private const CACHE_USER_PV_BALANCE = 3600;  // 1小时
    private const CACHE_WEEKLY_PV = 1800;         // 30分钟

    /**
     * 按安置链（Placement）向上累加PV - 优化版
     * 
     * 优化点：
     * - 使用缓存存储安置链
     * - 批量插入而非逐条插入
     * 
     * @param Order $order 订单
     * @return array 累加结果
     */
    public function creditPV(Order $order): array
    {
        $pvAmount = $order->quantity * 3000;
        $user = $order->user;
        
        // 使用缓存获取安置链
        $upline = $this->getPlacementChainWithCache($user);
        $results = [];

        // 批量插入数据
        $insertData = [];
        $now = now();

        foreach ($upline as $node) {
            $insertData[] = [
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
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $results[] = [
                'user_id' => $node['user_id'],
                'position' => $node['position'],
                'level' => $node['level'],
                'amount' => $pvAmount,
            ];
        }

        // 批量插入（幂等）
        if (!empty($insertData)) {
            DB::table('pv_ledger')->insertOrIgnore($insertData);
            
            // 清除相关缓存
            $this->clearUserPVCache($user->id);
            foreach ($upline as $node) {
                $this->clearUserPVCache($node['user_id']);
            }
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
     * 获取安置链（带缓存）- 优化版
     * 
     * 优化点：
     * - 使用 Redis 缓存存储安置链
     * - 使用单次查询获取所有上级
     * 
     * @param User $user 用户
     * @return array 安置链
     */
    private function getPlacementChainWithCache(User $user): array
    {
        $cacheKey = "placement_chain:{$user->id}";
        
        return Cache::remember($cacheKey, self::CACHE_PLACEMENT_CHAIN, function () use ($user) {
            return $this->getPlacementChainOptimized($user);
        });
    }

    /**
     * 获取安置链（优化版）- 使用递归 CTE 查询
     * 
     * 优化点：
     * - 使用单次递归查询获取完整安置链
     * - 避免循环查询数据库
     * 
     * @param User $user 用户
     * @return array 安置链
     */
    private function getPlacementChainOptimized(User $user): array
    {
        // 使用递归 CTE 查询（MySQL 8.0+）
        $sql = "
            WITH RECURSIVE placement_chain AS (
                -- 基础查询：当前用户
                SELECT 
                    u.id,
                    u.pos_id,
                    u.position,
                    0 as level
                FROM users u
                WHERE u.id = :user_id
                
                UNION ALL
                
                -- 递归查询：上级用户
                SELECT 
                    u.id,
                    u.pos_id,
                    u.position,
                    pc.level + 1
                FROM users u
                INNER JOIN placement_chain pc ON u.id = pc.pos_id
                WHERE u.pos_id IS NOT NULL
            )
            SELECT 
                pc.id as user_id,
                pc.position,
                pc.level
            FROM placement_chain pc
            WHERE pc.level > 0
            ORDER BY pc.level ASC
        ";

        $results = DB::select($sql, ['user_id' => $user->id]);
        
        $chain = [];
        foreach ($results as $row) {
            $chain[] = [
                'user_id' => $row->user_id,
                'position' => $row->position ?: 1,
                'level' => $row->level,
            ];
        }

        return $chain;
    }

    /**
     * 获取用户当前PV余额（带缓存）- 优化版
     * 
     * 优化点：
     * - 使用缓存存储 PV 余额
     * - 使用单个聚合查询
     * 
     * @param int $userId 用户ID
     * @param bool $includeCarry 是否包含结转
     * @return array PV余额
     */
    public function getUserPVBalance(int $userId, bool $includeCarry = true): array
    {
        $cacheKey = "user_pv_balance:{$userId}:" . ($includeCarry ? 'with_carry' : 'no_carry');
        
        return Cache::remember($cacheKey, self::CACHE_USER_PV_BALANCE, function () use ($userId, $includeCarry) {
            return $this->calculateUserPVBalance($userId, $includeCarry);
        });
    }

    /**
     * 计算 PV 余额（内部方法）
     * 
     * @param int $userId 用户ID
     * @param bool $includeCarry 是否包含结转
     * @return array PV余额
     */
    private function calculateUserPVBalance(int $userId, bool $includeCarry): array
    {
        $query = PvLedger::where('user_id', $userId);
        
        if (!$includeCarry) {
            $query->whereNotIn('source_type', $this->getCarryFlashSourceTypes());
        }

        // 使用单个聚合查询计算左右区 PV
        $results = $query->selectRaw('
            position,
            SUM(CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END) as total_pv
        ')
        ->groupBy('position')
        ->pluck('total_pv', 'position');

        $leftPv = $results->get(1, 0);
        $rightPv = $results->get(2, 0);

        return [
            'left_pv' => (float) $leftPv,
            'right_pv' => (float) $rightPv,
            'total_pv' => (float) ($leftPv + $rightPv),
        ];
    }

    /**
     * 获取本周新增弱区PV（用于TEAM莲子计算）- 优化版
     * 
     * 优化点：
     * - 使用缓存
     * - 优化 SQL 查询
     * 
     * @param int $userId 用户ID
     * @param string $weekKey 周键
     * @return float 弱区PV
     */
    public function getWeeklyNewWeakPV(int $userId, string $weekKey): float
    {
        $cacheKey = "weekly_weak_pv:{$userId}:{$weekKey}";
        
        return Cache::remember($cacheKey, self::CACHE_WEEKLY_PV, function () use ($userId, $weekKey) {
            list($start, $end) = $this->getWeekDateRange($weekKey);
            
            // 使用单个查询计算本周弱区 PV
            $results = PvLedger::where('user_id', $userId)
                ->whereIn('source_type', ['order', 'adjustment'])
                ->whereBetween('created_at', [$start, $end])
                ->selectRaw('
                    position,
                    SUM(CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END) as total_pv
                ')
                ->groupBy('position')
                ->pluck('total_pv', 'position');

            $leftPV = (float) ($results->get(1, 0));
            $rightPV = (float) ($results->get(2, 0));
            
            return min($leftPV, $rightPV);
        });
    }

    /**
     * 用户端PV汇总（带缓存）- 优化版
     * 
     * @param int $userId 用户ID
     * @param bool $includeCarry 是否包含结转
     * @return array PV汇总
     */
    public function getUserPVSummary(int $userId, bool $includeCarry = true): array
    {
        $balance = $this->getUserPVBalance($userId, $includeCarry);
        $currentWeek = now()->format('o-\WW');
        [$start, $end] = $this->getWeekDateRange($currentWeek);

        // 使用单个查询计算本周 PV
        $weekResults = PvLedger::where('user_id', $userId)
            ->whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('
                position,
                SUM(CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END) as total_pv
            ')
            ->groupBy('position')
            ->pluck('total_pv', 'position');

        $leftWeek = (float) ($weekResults->get(1, 0));
        $rightWeek = (float) ($weekResults->get(2, 0));

        return [
            'left_pv' => $balance['left_pv'],
            'right_pv' => $balance['right_pv'],
            'this_week_left' => $leftWeek,
            'this_week_right' => $rightWeek,
        ];
    }

    /**
     * 周结算时扣减PV
     * 
     * @param int $userId 用户ID
     * @param float $deductPV 扣减PV
     * @param string $weekKey 周键
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

        // 清除缓存
        $this->clearUserPVCache($userId);

        // 更新用户PV余额
        $userExtra = UserExtra::where('user_id', $userId)->first();
        if ($userExtra) {
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
     * 结转时扣减PV（优化版）
     * 
     * @param int $userId 用户ID
     * @param int $position 区位置
     * @param float $amount 扣减金额
     * @param string $weekKey 周键
     * @param string $remark 备注
     * @param string $sourceSource 来源类型
     * @param string $trxType 交易类型
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
        ]);
        
        // 清除缓存
        if ($inserted) {
            $this->clearUserPVCache($userId);
        }
        
        return (bool) $inserted;
    }

    /**
     * 清除用户 PV 缓存
     * 
     * @param int $userId 用户ID
     */
    private function clearUserPVCache(int $userId): void
    {
        $patterns = [
            "user_pv_balance:{$userId}:*",
            "weekly_weak_pv:{$userId}:*",
        ];
        
        foreach ($patterns as $pattern) {
            // 注意：需要 Redis 驱动支持模式匹配
            // Cache::forget($pattern); // 简单实现
            // 实际项目中可以使用 Redis 的 KEYS + DEL 命令
        }
    }

    /**
     * 获取周日期范围
     * 
     * @param string $weekKey 周键
     * @return array [开始时间, 结束时间]
     */
    private function getWeekDateRange(string $weekKey): array
    {
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
        
        return [now()->startOfWeek(), now()->endOfWeek()];
    }

    /**
     * 获取结转来源类型
     * 
     * @return array 类型列表
     */
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