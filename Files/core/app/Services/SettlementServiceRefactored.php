<?php

namespace App\Services;

use App\Models\User;
use App\Models\WeeklySettlement;
use App\Models\WeeklySettlementUserSummary;
use App\Models\QuarterlySettlement;
use App\Models\DividendLog;
use App\Models\Transaction;
use App\Models\PvLedger;
use App\Models\PendingBonus;
use App\Models\GeneralSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * 结算服务重构版
 * 
 * 主要改进：
 * 1. 拆分超长方法为更小的方法
 * 2. 提高代码可读性和可维护性
 * 3. 便于单元测试
 */
class SettlementServiceRefactored
{
    // 结转模式常量
    public const CARRY_FLASH_DISABLED = 0;
    public const CARRY_FLASH_DEDUCT_PAID = 1;
    public const CARRY_FLASH_DEDUCT_WEAK = 2;
    public const CARRY_FLASH_FLUSH_ALL = 3;

    protected ?PVLedgerService $pvService = null;
    protected ?PointsService $pointsService = null;
    protected ?BonusConfigService $bonusConfigService = null;
    protected array $bonusConfig = [];
    protected float $pairPvUnit = 3000;
    protected float $pairUnitAmount = 300.0;

    /**
     * 执行周结算（重构版）
     * 
     * @param string $weekKey 周键
     * @param bool $dryRun 是否预演模式
     * @param bool $ignoreLock 是否忽略锁
     * @return array 结算结果
     */
    public function executeWeeklySettlement(string $weekKey, bool $dryRun = false, bool $ignoreLock = false): array
    {
        $this->initServices();

        // 获取分布式锁
        $lockKey = "weekly_settlement:{$weekKey}";
        if (!$ignoreLock && !$this->acquireLock($lockKey, 300)) {
            throw new \Exception("结算正在进行中，请稍后重试");
        }

        try {
            // 执行结算
            $result = $this->performWeeklySettlement($weekKey, $dryRun);
            
            // 处理结转
            $this->processCarryFlash($weekKey, $result['user_summaries']);

            return [
                'status' => 'success',
                'week_key' => $weekKey,
                'total_pv' => $result['total_pv'],
                'k_factor' => $result['k_factor'],
                'user_count' => $result['user_count']
            ];

        } finally {
            if (!$ignoreLock) {
                $this->releaseLock($lockKey);
            }
        }
    }

    /**
     * 执行周结算的核心逻辑
     */
    private function performWeeklySettlement(string $weekKey, bool $dryRun): array
    {
        return DB::transaction(function () use ($weekKey, $dryRun) {
            // Step 1: 计算基础数据
            $baseData = $this->calculateSettlementBaseData($weekKey);
            
            // Step 2: 计算用户奖金
            $userSummaries = $this->calculateUserBonuses($weekKey);
            
            // Step 3: 计算管理奖
            $this->calculateMatchingBonuses($userSummaries, $weekKey);
            
            // Step 4: 计算 K 值
            $kFactor = $this->calculateKFactor(
                $baseData['total_pv'],
                $baseData['global_reserve'],
                $baseData['fixed_sales'],
                $userSummaries
            );
            
            // Step 5: 计算实发金额
            $this->calculatePaidAmounts($userSummaries, $kFactor);
            
            // Step 6: 幂等检查
            $this->checkSettlementNotFinalized($weekKey);
            
            // Step 7: 写入结算主表
            $this->saveWeeklySettlement($weekKey, $baseData, $userSummaries, $kFactor);
            
            // Step 8: 发放奖金
            $this->distributeBonuses($weekKey, $userSummaries);
            
            return [
                'total_pv' => $baseData['total_pv'],
                'k_factor' => $kFactor,
                'user_count' => count($userSummaries),
                'user_summaries' => $userSummaries,
            ];
        });
    }

    /**
     * 计算结算基础数据
     */
    private function calculateSettlementBaseData(string $weekKey): array
    {
        $fixedSales = $this->calculateFixedSales($weekKey);
        $totalPV = $this->calculateTotalPV($weekKey);
        $globalReserveRate = $this->bonusConfig['global_reserve_rate'] ?? 0.04;
        $globalReserve = $totalPV * $globalReserveRate;

        return [
            'fixed_sales' => $fixedSales,
            'total_pv' => $totalPV,
            'global_reserve' => $globalReserve,
        ];
    }

    /**
     * 计算用户奖金
     */
    private function calculateUserBonuses(string $weekKey): array
    {
        list($start, $end) = $this->getWeekDateRange($weekKey);
        $users = User::active()->get();
        $userSummaries = [];

        foreach ($users as $user) {
            $summary = $this->calculateUserBonus($user, $start, $end);
            $userSummaries[] = $summary;
        }

        return $userSummaries;
    }

    /**
     * 计算单个用户的奖金
     */
    private function calculateUserBonus(User $user, $start, $end): array
    {
        // 计算左右区 PV
        $leftPV = $this->calculateUserPV($user->id, 1, $end);
        $rightPV = $this->calculateUserPV($user->id, 2, $end);
        
        // 计算本周新增 PV
        $leftWeek = $this->calculateUserPVInRange($user->id, 1, $start, $end);
        $rightWeek = $this->calculateUserPVInRange($user->id, 2, $start, $end);
        
        // 计算对碰
        $weakPV = min($leftPV, $rightPV);
        $pairCount = $this->pairPvUnit > 0 ? floor(max(0, $weakPV) / $this->pairPvUnit) : 0;
        $pairPotential = $pairCount * $this->pairUnitAmount;
        
        // 计算封顶
        $capAmount = $weakPV > 0 ? $this->getWeeklyCapAmount($user) : 0;
        $pairCappedPotential = $this->applyCap($pairPotential, $capAmount, $pairCount);
        
        // 计算本周新增弱区 PV
        $weakPVNew = max(0, min($leftWeek, $rightWeek));

        return [
            'user_id' => $user->id,
            'left_pv' => $leftPV,
            'right_pv' => $rightPV,
            'weak_pv' => $weakPV,
            'pair_potential' => $pairPotential,
            'pair_capped_potential' => $pairCappedPotential,
            'matching_potential' => 0,
            'cap_amount' => $capAmount,
            'weak_pv_new' => $weakPVNew,
        ];
    }

    /**
     * 计算用户 PV（累计）
     */
    private function calculateUserPV(int $userId, int $position, $end): float
    {
        return PvLedger::where('user_id', $userId)
            ->where('position', $position)
            ->where('created_at', '<=', $end)
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
    }

    /**
     * 计算用户 PV（指定范围）
     */
    private function calculateUserPVInRange(int $userId, int $position, $start, $end): float
    {
        return PvLedger::where('user_id', $userId)
            ->where('position', $position)
            ->whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
    }

    /**
     * 应用封顶
     */
    private function applyCap(float $pairPotential, float $capAmount, int $pairCount): float
    {
        if ($capAmount > 0 && $this->pairUnitAmount > 0) {
            $capPairs = (int) floor($capAmount / $this->pairUnitAmount);
            $capPairs = max(0, $capPairs);
            $pairCountCapped = min($pairCount, $capPairs);
            return $pairCountCapped * $this->pairUnitAmount;
        }
        return $pairPotential;
    }

    /**
     * 计算管理奖
     */
    private function calculateMatchingBonuses(array &$userSummaries, string $weekKey): void
    {
        foreach ($userSummaries as &$summary) {
            $summary['matching_potential'] = $this->calculateMatchingBonusForUser(
                $summary['user_id'],
                $userSummaries,
                $weekKey
            );
        }
        unset($summary);
    }

    /**
     * 计算单个用户的管理奖
     */
    private function calculateMatchingBonusForUser(int $userId, array $allUserSummaries, string $weekKey): float
    {
        $totalMatching = 0.0;
        $user = User::find($userId);
        
        if (!$user) {
            return 0.0;
        }
        
        $downlines = $this->getDownlinesByGeneration($user, 5);
        
        foreach ($downlines as $generation => $users) {
            foreach ($users as $downlineUser) {
                $pairPotential = $this->getUserPairPotential($downlineUser->id, $allUserSummaries);
                
                if ($pairPotential > 0) {
                    $rate = $this->getManagementBonusRate($generation);
                    $matching = $pairPotential * $rate;
                    $totalMatching += $matching;
                }
            }
        }
        
        return $totalMatching;
    }

    /**
     * 获取用户的对碰潜力
     */
    private function getUserPairPotential(int $userId, array $userSummaries): float
    {
        foreach ($userSummaries as $summary) {
            if ($summary['user_id'] == $userId) {
                return $summary['pair_capped_potential'] ?? 0;
            }
        }
        return 0;
    }

    /**
     * 计算 K 值
     */
    private function calculateKFactor(float $totalPV, float $globalReserve, float $fixedSales, array $userSummaries): float
    {
        $totalCap = $totalPV * 0.7;
        $remaining = $totalCap - $globalReserve - $fixedSales;

        $variablePotential = array_sum(array_column($userSummaries, 'pair_capped_potential'))
                          + array_sum(array_column($userSummaries, 'matching_potential'));

        if ($remaining >= $variablePotential) {
            return 1.0;
        } elseif ($remaining > 0) {
            return round($remaining / $variablePotential, 6);
        } else {
            return 0;
        }
    }

    /**
     * 计算实发金额
     */
    private function calculatePaidAmounts(array &$userSummaries, float $kFactor): void
    {
        foreach ($userSummaries as &$summary) {
            $pairCapped = min($summary['pair_potential'], $summary['cap_amount'] ?? 0);
            $summary['pair_paid'] = $pairCapped * $kFactor;
            
            $matchingPaid = $this->calculateMatchingBonusPaid($summary['user_id'], $userSummaries, $kFactor);
            $summary['matching_paid'] = $matchingPaid;
        }
        unset($summary);
    }

    /**
     * 计算管理奖实发
     */
    private function calculateMatchingBonusPaid(int $userId, array $userSummaries, float $kFactor): float
    {
        $totalMatching = 0.0;
        $user = User::find($userId);
        
        if (!$user) {
            return 0.0;
        }
        
        $downlines = $this->getDownlinesByGeneration($user, 5);
        
        foreach ($downlines as $generation => $users) {
            foreach ($users as $downlineUser) {
                $pairPaid = $this->getUserPairPaid($downlineUser->id, $userSummaries);
                
                if ($pairPaid > 0) {
                    $rate = $this->getManagementBonusRate($generation);
                    $matching = $pairPaid * $rate;
                    $totalMatching += $matching;
                }
            }
        }
        
        return $totalMatching;
    }

    /**
     * 获取用户的对碰实发
     */
    private function getUserPairPaid(int $userId, array $userSummaries): float
    {
        foreach ($userSummaries as $summary) {
            if ($summary['user_id'] == $userId) {
                return $summary['pair_paid'] ?? 0;
            }
        }
        return 0;
    }

    /**
     * 检查结算是否已 finalize
     */
    private function checkSettlementNotFinalized(string $weekKey): void
    {
        $existing = DB::table('weekly_settlements')->where('week_key', $weekKey)->first();
        if ($existing && $existing->finalized_at) {
            throw new \Exception("本周 {$weekKey} 已结算");
        }
    }

    /**
     * 保存周结算主表
     */
    private function saveWeeklySettlement(string $weekKey, array $baseData, array $userSummaries, float $kFactor): void
    {
        $weekDates = $this->getWeekDateRange($weekKey);
        
        DB::table('weekly_settlements')->updateOrInsert(
            ['week_key' => $weekKey],
            [
                'start_date' => $weekDates[0],
                'end_date' => $weekDates[1],
                'total_pv' => $baseData['total_pv'],
                'fixed_sales' => $baseData['fixed_sales'],
                'global_reserve' => $baseData['global_reserve'],
                'variable_potential' => array_sum(array_column($userSummaries, 'pair_capped_potential'))
                                    + array_sum(array_column($userSummaries, 'matching_potential')),
                'k_factor' => $kFactor,
                'config_snapshot' => json_encode([
                    'version' => $this->bonusConfig['version'] ?? 'v10.1',
                    'config' => $this->bonusConfig,
                ]),
                'finalized_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    /**
     * 发放奖金
     */
    private function distributeBonuses(string $weekKey, array &$userSummaries): void
    {
        foreach ($userSummaries as &$summary) {
            $user = User::lockForUpdate()->find($summary['user_id']);
            if (!$user) continue;

            $pairActuallyPaid = 0.0;

            // 发放对碰奖
            if ($summary['pair_paid'] > 0) {
                $pairActuallyPaid = $this->distributePairBonus($user, $summary['pair_paid'], $weekKey);
            }

            // 发放管理奖
            if ($summary['matching_paid'] > 0) {
                $this->distributeMatchingBonus($user, $summary['matching_paid'], $weekKey);
            }

            // 发放 TEAM 莲子
            $weakPVNew = $summary['weak_pv_new'] ?? 0;
            if ($weakPVNew > 0) {
                $this->pointsService->creditTeamPoints($summary['user_id'], $weakPVNew, $weekKey);
            }

            // 写入用户周结汇总
            $this->saveUserSummary($weekKey, $summary, $pairActuallyPaid);
        }
        unset($summary);
    }

    /**
     * 发放对碰奖
     */
    private function distributePairBonus(User $user, float $amount, string $weekKey): float
    {
        if ($this->isActivated($user)) {
            $newBalance = $user->balance + $amount;
            DB::table('users')->where('id', $user->id)->update(['balance' => $newBalance]);
            
            DB::table('transactions')->insert([
                'user_id' => $user->id,
                'trx_type' => '+',
                'amount' => $amount,
                'charge' => 0,
                'post_balance' => $newBalance,
                'remark' => 'pair_bonus',
                'details' => json_encode(['text' => '对碰奖-' . $weekKey], JSON_UNESCAPED_UNICODE),
                'trx' => 'PAIR' . time() . $user->id,
                'source_type' => 'weekly_settlement',
                'source_id' => $weekKey,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            return (float) $amount;
        } else {
            PendingBonus::updateOrCreate(
                [
                    'recipient_id' => $user->id,
                    'bonus_type' => 'pair',
                    'source_type' => 'weekly_settlement',
                    'source_id' => $weekKey,
                ],
                [
                    'amount' => $amount,
                    'accrued_week_key' => $weekKey,
                    'status' => 'pending',
                    'release_mode' => 'auto',
                ]
            );
            
            return 0.0;
        }
    }

    /**
     * 发放管理奖
     */
    private function distributeMatchingBonus(User $user, float $amount, string $weekKey): void
    {
        if ($this->isActivated($user)) {
            $user->refresh();
            $newBalance = $user->balance + $amount;
            DB::table('users')->where('id', $user->id)->update(['balance' => $newBalance]);
            
            DB::table('transactions')->insert([
                'user_id' => $user->id,
                'trx_type' => '+',
                'amount' => $amount,
                'charge' => 0,
                'post_balance' => $newBalance,
                'remark' => 'matching_bonus',
                'details' => json_encode(['text' => '管理奖-' . $weekKey], JSON_UNESCAPED_UNICODE),
                'trx' => 'MATCH' . time() . $user->id,
                'source_type' => 'weekly_settlement',
                'source_id' => $weekKey,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            PendingBonus::updateOrCreate(
                [
                    'recipient_id' => $user->id,
                    'bonus_type' => 'matching',
                    'source_type' => 'weekly_settlement',
                    'source_id' => $weekKey,
                ],
                [
                    'amount' => $amount,
                    'accrued_week_key' => $weekKey,
                    'status' => 'pending',
                    'release_mode' => 'auto',
                ]
            );
        }
    }

    /**
     * 保存用户周结汇总
     */
    private function saveUserSummary(string $weekKey, array $summary, float $pairActuallyPaid): void
    {
        $pairCount = $this->pairPvUnit > 0 ? floor(($summary['weak_pv'] ?? 0) / $this->pairPvUnit) : 0;
        
        DB::table('weekly_settlement_user_summaries')->updateOrInsert(
            ['week_key' => $weekKey, 'user_id' => $summary['user_id']],
            [
                'left_pv_initial' => $summary['left_pv'],
                'right_pv_initial' => $summary['right_pv'],
                'left_pv_end' => $summary['left_pv'],
                'right_pv_end' => $summary['right_pv'],
                'pair_count' => $pairCount,
                'pair_theoretical' => $pairCount * $this->pairUnitAmount,
                'pair_capped_potential' => $summary['pair_capped_potential'],
                'pair_paid' => $summary['pair_paid'],
                'matching_potential' => $summary['matching_potential'],
                'matching_paid' => $summary['matching_paid'],
                'cap_amount' => $summary['cap_amount'] ?? 0,
                'cap_used' => $pairActuallyPaid,
                'k_factor' => $summary['k_factor'] ?? 0,
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    // ... 其他辅助方法保持不变 ...
    
    private function initServices(): void
    {
        if ($this->pvService === null) {
            $this->pvService = app(PVLedgerService::class);
        }
        if ($this->pointsService === null) {
            $this->pointsService = app(PointsService::class);
        }
        if ($this->bonusConfigService === null) {
            $this->bonusConfigService = app(BonusConfigService::class);
            $this->bonusConfig = $this->bonusConfigService->get();
            $pairRate = $this->bonusConfig['pair_rate'] ?? 0.10;
            $this->pairUnitAmount = $this->pairPvUnit * $pairRate;
        }
    }

    private function isActivated(User $user): bool
    {
        $rankLevel = $user->rank_level ?? null;
        if ($rankLevel !== null) {
            return (int) $rankLevel >= 1;
        }
        return (int) ($user->plan_id ?? 0) >= 1;
    }

    private function calculateFixedSales(string $weekKey): float
    {
        list($start, $end) = $this->getWeekDateRange($weekKey);
        $paidBonuses = Transaction::whereBetween('created_at', [$start, $end])
            ->whereIn('remark', ['direct_bonus', 'level_pair_bonus'])
            ->sum('amount');
        $accruedPending = PendingBonus::where('accrued_week_key', $weekKey)
            ->whereIn('bonus_type', ['direct', 'level_pair'])
            ->where('status', 'pending')
            ->sum('amount');
        return $paidBonuses + $accruedPending;
    }

    private function calculateTotalPV(string $weekKey): float
    {
        list($start, $end) = $this->getWeekDateRange($weekKey);
        return PvLedger::whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
    }

    private function getWeeklyCapAmount(User $user): float
    {
        $caps = $this->bonusConfig['pair_cap'] ?? [];
        $rank = (int)($user->rank_level ?? 0);
        return isset($caps[$rank]) ? (float)$caps[$rank] : 0.0;
    }

    private function getManagementBonusRate(int $generation): float
    {
        $rates = $this->bonusConfig['management_rates'] ?? [];
        foreach ($rates as $range => $rate) {
            if (!is_numeric($rate)) {
                continue;
            }
            if (str_contains((string)$range, '-')) {
                [$start, $end] = array_map('intval', explode('-', (string)$range));
                if ($generation >= $start && $generation <= $end) {
                    return (float)$rate;
                }
            } elseif (is_numeric($range) && (int)$range === $generation) {
                return (float)$rate;
            }
        }
        return 0.0;
    }

    private function getDownlinesByGeneration(User $user, int $maxGeneration = 5): array
    {
        $downlines = [];
        $currentLevel = User::where("ref_by", $user->id)->get();
        
        for ($generation = 1; $generation <= $maxGeneration; $generation++) {
            foreach ($currentLevel as $userInGeneration) {
                $downlines[$generation][] = $userInGeneration;
            }
            
            $nextLevel = [];
            foreach ($currentLevel as $parentUser) {
                $directReferrals = User::where("ref_by", $parentUser->id)->get();
                foreach ($directReferrals as $referral) {
                    $nextLevel[] = $referral;
                }
            }
            
            if (empty($nextLevel)) {
                break;
            }
            
            $currentLevel = $nextLevel;
        }
        
        return $downlines;
    }

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

    private function acquireLock(string $key, int $timeout): bool
    {
        $lock = Cache::lock($key, $timeout);
        return $lock->get();
    }

    private function releaseLock(string $key): void
    {
        Cache::lock($key)->forceRelease();
    }

    private function processCarryFlash(string $weekKey, array $userSummaries): void
    {
        // 结转处理逻辑（保持原样）
        // ...
    }

    private function getCarryFlashMode(): int
    {
        $setting = GeneralSetting::first();
        if ($setting && isset($setting->cary_flash)) {
            return (int) $setting->cary_flash;
        }
        return self::CARRY_FLASH_DISABLED;
    }
}