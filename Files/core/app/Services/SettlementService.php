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

class SettlementService
{
    // 结转模式常量
    public const CARRY_FLASH_DISABLED = 0;      // 不结转（默认）
    public const CARRY_FLASH_DEDUCT_PAID = 1;   // 扣除已发放 PV
    public const CARRY_FLASH_DEDUCT_WEAK = 2;   // 扣除弱区 PV
    public const CARRY_FLASH_FLUSH_ALL = 3;     // 清空全部 PV

    protected ?PVLedgerService $pvService = null;
    protected ?PointsService $pointsService = null;
    protected ?BonusConfigService $bonusConfigService = null;
    protected array $bonusConfig = [];
    protected float $pairPvUnit = 3000;
    protected float $pairUnitAmount = 300.0;

    /**
     * 延迟初始化服务
     */
    protected function initServices(): void
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

    /**
     * 获取bonusConfigService实例
     */
    protected function getBonusConfigService(): BonusConfigService
    {
        if ($this->bonusConfigService === null) {
            $this->bonusConfigService = app(BonusConfigService::class);
            $this->bonusConfig = $this->bonusConfigService->get();
            $pairRate = $this->bonusConfig['pair_rate'] ?? 0.10;
            $this->pairUnitAmount = $this->pairPvUnit * $pairRate;
        }
        return $this->bonusConfigService;
    }

    /**
     * 执行周结算
     * 
     * @param string $weekKey 周键，如 '2025-W51'
     * @param bool $dryRun 是否预演模式（不写入数据库）
     * @return array 结算结果
     */
    public function executeWeeklySettlement(string $weekKey, bool $dryRun = false, bool $ignoreLock = false): array
    {
        // 延迟初始化服务
        $this->initServices();

        // 分布式锁：防止同一week_key并发执行
        $lockKey = "weekly_settlement:{$weekKey}";
        if (!$ignoreLock && !$this->acquireLock($lockKey, 300)) { // 5分钟超时
            throw new \Exception("结算正在进行中，请稍后重试");
        }

        try {
            $userSummaries = [];
            // Step 1-6: 在事务内计算并写入，确保数据一致性
            $result = DB::transaction(function () use ($weekKey, &$userSummaries) {
                // Step 1: 统计本周刚性支出（FixedSales）
                $fixedSales = $this->calculateFixedSales($weekKey);

                // Step 2: 统计本周总PV
                $totalPV = $this->calculateTotalPV($weekKey);

                // Step 3: 功德池预留
                $this->initServices();
                $globalReserveRate = $this->bonusConfig['global_reserve_rate'] ?? 0.04;
                $globalReserve = $totalPV * $globalReserveRate;

                // Step 4: 计算每用户对碰理论
                $userSummaries = $this->calculateUserBonuses($weekKey);

                // Step 4b: 计算管理奖潜力（基于下级封顶后对碰潜力）
                // 注意：管理奖实际发放会在 Step 6 基于对碰实发计算
                foreach ($userSummaries as &$summary) {
                    $summary['matching_potential'] = $this->calculateMatchingBonusForUser($summary['user_id'], $userSummaries, $weekKey);
                }
                unset($summary);

                // Step 5: 计算K值
                $kFactor = $this->calculateKFactor($totalPV, $globalReserve, $fixedSales, $userSummaries);

                // Step 6: 计算实发金额（封顶优先 → 再K）
                // 公式：pairPaid = min(potential, cap) × k
                // 封顶差额不结转，K差额可结转
                foreach ($userSummaries as &$summary) {
                    $pairPotential = $summary['pair_potential'] ?? 0;
                    $capAmount = $summary['cap_amount'] ?? 0;
                    
                    // 先应用封顶（取较小值）
                    $pairCapped = min($pairPotential, $capAmount);
                    
                    // 再应用 K 值
                    $pairPaid = $pairCapped * $kFactor;
                    $summary['pair_paid'] = $pairPaid;
                    
                    // 管理奖基于下级实际获得的对碰奖（pair_capped × K）
                    // 重新计算管理奖，确保基于对碰实发
                    $matchingPaid = $this->calculateMatchingBonusPaid($summary['user_id'], $userSummaries, $kFactor);
                    $summary['matching_paid'] = $matchingPaid;
                }
                unset($summary);

                // 幂等检查
                $existing = DB::table('weekly_settlements')->where('week_key', $weekKey)->first();
                if ($existing && $existing->finalized_at) {
                    throw new \Exception("本周 {$weekKey} 已结算");
                }

                // 写入周结算主表
                $weekDates = $this->getWeekDateRange($weekKey);
                DB::table('weekly_settlements')->updateOrInsert(
                    ['week_key' => $weekKey],
                    [
                        'start_date' => $weekDates[0],
                        'end_date' => $weekDates[1],
                        'total_pv' => $totalPV,
                        'fixed_sales' => $fixedSales,
                        'global_reserve' => $globalReserve,
                        // K值基于封顶后的潜力计算（封顶优先 → 再K）
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

                // 逐用户发放
                foreach ($userSummaries as &$summary) {
                    $user = User::lockForUpdate()->find($summary['user_id']);
                    if (!$user) continue;

                    $initialLeft = $summary['left_pv'] ?? 0;
                    $initialRight = $summary['right_pv'] ?? 0;
                    $weakPV = $summary['weak_pv'] ?? 0;
                    $pairCount = $this->pairPvUnit > 0 ? floor($weakPV / $this->pairPvUnit) : 0;
                    $pairActuallyPaid = 0.0;

                    // 对碰入账
                    if ($summary['pair_paid'] > 0) {
                        if ($this->isActivated($user)) {
                            $newBalance = $user->balance + $summary['pair_paid'];
                            DB::table('users')->where('id', $user->id)->update(['balance' => $newBalance]);
                            DB::table('transactions')->insert([
                                'user_id' => $summary['user_id'],
                                'trx_type' => '+',
                                'amount' => $summary['pair_paid'],
                                'charge' => 0,
                                'post_balance' => $newBalance,
                                'remark' => 'pair_bonus',
                                'details' => json_encode(['text' => '对碰奖-' . $weekKey], JSON_UNESCAPED_UNICODE),
                                'trx' => 'PAIR' . time() . $summary['user_id'],
                                'source_type' => 'weekly_settlement',
                                'source_id' => $weekKey,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $pairActuallyPaid = (float) $summary['pair_paid'];
                        } else {
                            PendingBonus::updateOrCreate(
                                [
                                    'recipient_id' => $summary['user_id'],
                                    'bonus_type' => 'pair',
                                    'source_type' => 'weekly_settlement',
                                    'source_id' => $weekKey,
                                ],
                                [
                                    'amount' => $summary['pair_paid'],
                                    'accrued_week_key' => $weekKey,
                                    'status' => 'pending',
                                    'release_mode' => 'auto',
                                ]
                            );
                        }
                    }

                    // 管理入账
                    if ($summary['matching_paid'] > 0) {
                        if ($this->isActivated($user)) {
                            $user->refresh();
                            $newBalance = $user->balance + $summary['matching_paid'];
                            DB::table('users')->where('id', $user->id)->update(['balance' => $newBalance]);
                            DB::table('transactions')->insert([
                                'user_id' => $summary['user_id'],
                                'trx_type' => '+',
                                'amount' => $summary['matching_paid'],
                                'charge' => 0,
                                'post_balance' => $newBalance,
                                'remark' => 'matching_bonus',
                                'details' => json_encode(['text' => '管理奖-' . $weekKey], JSON_UNESCAPED_UNICODE),
                                'trx' => 'MATCH' . time() . $summary['user_id'],
                                'source_type' => 'weekly_settlement',
                                'source_id' => $weekKey,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        } else {
                            PendingBonus::updateOrCreate(
                                [
                                    'recipient_id' => $summary['user_id'],
                                    'bonus_type' => 'matching',
                                    'source_type' => 'weekly_settlement',
                                    'source_id' => $weekKey,
                                ],
                                [
                                    'amount' => $summary['matching_paid'],
                                    'accrued_week_key' => $weekKey,
                                    'status' => 'pending',
                                    'release_mode' => 'auto',
                                ]
                            );
                        }
                    }

                    $summary['pair_paid_actual'] = $pairActuallyPaid;

                    // TEAM 莲子
                    $weakPVNew = $summary['weak_pv_new'] ?? 0;
                    if ($weakPVNew > 0) {
                        $this->pointsService->creditTeamPoints($summary['user_id'], $weakPVNew, $weekKey);
                    }

                    // 写入用户周结汇总（纯台账方案：end PV 与初始相同，无需扣除）
                    DB::table('weekly_settlement_user_summaries')->updateOrInsert(
                        ['week_key' => $weekKey, 'user_id' => $summary['user_id']],
                        [
                            'left_pv_initial' => $initialLeft,
                            'right_pv_initial' => $initialRight,
                            'left_pv_end' => $initialLeft,
                            'right_pv_end' => $initialRight,
                            'pair_count' => $pairCount,
                            'pair_theoretical' => $pairCount * $this->pairUnitAmount,
                            'pair_capped_potential' => $summary['pair_capped_potential'],
                            'pair_paid' => $summary['pair_paid'],
                            'matching_potential' => $summary['matching_potential'],
                            'matching_paid' => $summary['matching_paid'],
                            'cap_amount' => $summary['cap_amount'] ?? 0,
                            'cap_used' => $pairActuallyPaid,
                            'k_factor' => $kFactor,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]
                    );
                }
                unset($summary);

                return [
                    'total_pv' => $totalPV,
                    'k_factor' => $kFactor,
                    'user_count' => count($userSummaries)
                ];
            });

            // Step 7: 结转处理（事务提交后执行）
            $this->processCarryFlash($weekKey, $userSummaries);

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

    private function isActivated(User $user): bool
    {
        // V10.1 口径：rank_level >= 1 视为激活；兼容旧库用 plan_id 判断
        $rankLevel = $user->rank_level ?? null;
        if ($rankLevel !== null) {
            return (int) $rankLevel >= 1;
        }
        return (int) ($user->plan_id ?? 0) >= 1;
    }

    /**
     * 计算刚性支出（FixedSales）
     */
    private function calculateFixedSales(string $weekKey): float
    {
        list($start, $end) = $this->getWeekDateRange($weekKey);

        // 已发放的直推+层碰
        $paidBonuses = Transaction::whereBetween('created_at', [$start, $end])
            ->whereIn('remark', ['direct_bonus', 'level_pair_bonus'])
            ->sum('amount');

        // 应计预留（待处理奖金）
        $accruedPending = PendingBonus::where('accrued_week_key', $weekKey)
            ->whereIn('bonus_type', ['direct', 'level_pair'])
            ->where('status', 'pending')
            ->sum('amount');

        return $paidBonuses + $accruedPending;
    }

    /**
     * 统计本周总PV
     */
    private function calculateTotalPV(string $weekKey): float
    {
        list($start, $end) = $this->getWeekDateRange($weekKey);

        return $this->calculateOrderPVForRange($start, $end);
    }

    private function calculateOrderPVForRange($start, $end): float
    {
        // 支持正负业绩：正业绩 - 负业绩
        return PvLedger::whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
    }

    /**
     * 计算每用户对碰/管理理论 - 纯台账方案，支持正负业绩
     * 
     * 从 pv_ledger 台账聚合计算弱区 PV，支持退款负业绩自动抵扣
     */
    private function calculateUserBonuses(string $weekKey): array
    {
        list($start, $end) = $this->getWeekDateRange($weekKey);
        
        $users = User::active()->get();
        $userSummaries = [];

        foreach ($users as $user) {
            // 从 pv_ledger 聚合计算左右区 PV（累计余额，支持正负业绩混合）
            $leftPV = PvLedger::where('user_id', $user->id)
                ->where('position', 1)
                ->where('created_at', '<=', $end)
                ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
            
            $rightPV = PvLedger::where('user_id', $user->id)
                ->where('position', 2)
                ->where('created_at', '<=', $end)
                ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));

            // 本周新增 PV（用于 TEAM 莲子计算）
            $leftWeek = PvLedger::where('user_id', $user->id)
                ->where('position', 1)
                ->whereIn('source_type', ['order', 'adjustment'])
                ->whereBetween('created_at', [$start, $end])
                ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));

            $rightWeek = PvLedger::where('user_id', $user->id)
                ->where('position', 2)
                ->whereIn('source_type', ['order', 'adjustment'])
                ->whereBetween('created_at', [$start, $end])
                ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
            
            // 对碰计算（弱区 PV = min(left, right)，负值表示亏损）
            $weakPV = min($leftPV, $rightPV);
            $pairCount = $this->pairPvUnit > 0 ? floor(max(0, $weakPV) / $this->pairPvUnit) : 0;
            $pairPotential = $pairCount * $this->pairUnitAmount;

            // 周封顶额度（先封顶，再计算 K）
            $capAmount = $weakPV > 0 ? $this->getWeeklyCapAmount($user) : 0;
            $pairCountCapped = $pairCount;
            $capPV = 0.0;
            if ($capAmount > 0 && $this->pairUnitAmount > 0) {
                $capPairs = (int) floor($capAmount / $this->pairUnitAmount);
                $capPairs = max(0, $capPairs);
                $pairCountCapped = min($pairCount, $capPairs);
                $capPV = $capPairs > 0 ? $capPairs * $this->pairPvUnit : 0.0;
            }
            $pairCappedPotential = $pairCountCapped * $this->pairUnitAmount;

            // 管理奖（基于潜力，后续在 calculateMatchingBonusForUser 覆盖）
            $matchingPotential = 0;

            // 本周新增弱区 PV（用于 TEAM 莲子计算，仅计算正值）
            $weakPVNew = max(0, min($leftWeek, $rightWeek));

            $userSummaries[] = [
                'user_id' => $user->id,
                'left_pv' => $leftPV,
                'right_pv' => $rightPV,
                'weak_pv' => $weakPV,
                'pair_potential' => $pairPotential,      // 原始潜力（未封顶）
                'pair_capped_potential' => $pairCappedPotential, // 封顶后潜力
                'matching_potential' => $matchingPotential,
                'cap_amount' => $capAmount,
                'cap_pv' => $capPV,
                'weak_pv_new' => $weakPVNew,
            ];
        }

        return $userSummaries;
    }

    /**
     * 计算K值（基于封顶后的潜力）
     */
    private function calculateKFactor(float $totalPV, float $globalReserve, float $fixedSales, array $userSummaries): float
    {
        $totalCap = $totalPV * 0.7; // 70%总拨出
        $remaining = $totalCap - $globalReserve - $fixedSales;

        // 使用封顶后的潜力计算 K 值
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
     * 获取周封顶额度
     */
    private function getWeeklyCapAmount(User $user): float
    {
        $caps = $this->bonusConfig['pair_cap'] ?? [];
        $rank = (int)($user->rank_level ?? 0);

        return isset($caps[$rank]) ? (float)$caps[$rank] : 0.0;
    }

    /**
     * 计算管理奖
     */

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
     * 获取分布式锁
     */
    private function acquireLock(string $key, int $timeout): bool
    {
        $lock = Cache::lock($key, $timeout);
        return $lock->get();
    }

    /**
     * 释放分布式锁
     */
    private function releaseLock(string $key): void
    {
        Cache::lock($key)->forceRelease();
    }

    /**
     * 执行季度分红
     */
    public function executeQuarterlySettlement(string $quarterKey, bool $dryRun = false): array
    {
        // 延迟初始化服务
        $this->initServices();

        [$start, $end] = $this->getQuarterDateRange($quarterKey);

        $totalPV = $this->calculateOrderPVForRange($start, $end);

        $poolStockistRate = $this->bonusConfig['pool_stockist_rate'] ?? 0.01;
        $poolLeaderRate = $this->bonusConfig['pool_leader_rate'] ?? 0.03;
        $poolStockist = $totalPV * $poolStockistRate;
        $poolLeader = $totalPV * $poolLeaderRate;

        // 消费商池资格：累计请购>=3 且当季活跃（last_activity_date落在当季）
        $stockists = User::where('personal_purchase_count', '>=', 3)
            ->whereBetween(DB::raw('COALESCE(last_activity_date, created_at)'), [$start->toDateString(), $end->toDateString()])
            ->get();

        $totalShares = $stockists->sum(fn($u) => $u->personal_purchase_count ?? 0);
        $unitStockist = $totalShares > 0 ? $poolStockist / $totalShares : 0;

        // 领导人池资格：有职级且季度弱区PV>=10000
        $leaders = User::whereNotNull('leader_rank_code')
            ->where('leader_rank_multiplier', '>', 0)
            ->get()
            ->filter(function ($u) use ($start, $end) {
                $weak = $this->getQuarterWeakPV($u->id, $start, $end);
                return $weak >= 10000;
            })
            ->values();

        $totalScore = 0;
        $leaderScores = [];
        foreach ($leaders as $leader) {
            $weak = $this->getQuarterWeakPV($leader->id, $start, $end);
            $score = $weak * $leader->leader_rank_multiplier;
            $leaderScores[$leader->id] = ['weak' => $weak, 'score' => $score];
            $totalScore += $score;
        }

        $unitLeader = $totalScore > 0 ? $poolLeader / $totalScore : 0;

        if ($dryRun) {
            return [
                'status' => 'success',
                'dry_run' => true,
                'quarter_key' => $quarterKey,
                'total_pv' => $totalPV,
                'pool_stockist' => $poolStockist,
                'pool_leader' => $poolLeader,
                'stockist_count' => $stockists->count(),
                'leader_count' => $leaders->count(),
                'unit_stockist' => $unitStockist,
                'unit_leader' => $unitLeader,
            ];
        }

        DB::transaction(function () use ($quarterKey, $start, $end, $totalPV, $poolStockist, $poolLeader, $unitStockist, $unitLeader, $stockists, $leaders, $leaderScores) {
            $existing = QuarterlySettlement::where('quarter_key', $quarterKey)->first();
            if ($existing && $existing->finalized_at) {
                throw new \Exception("本季度 {$quarterKey} 已结算");
            }

            QuarterlySettlement::updateOrCreate(
                ['quarter_key' => $quarterKey],
                [
                    'start_date' => $start,
                    'end_date' => $end,
                    'total_pv' => $totalPV,
                    'pool_stockist' => $poolStockist,
                    'pool_leader' => $poolLeader,
                    'total_shares' => $stockists->sum(fn($u) => $u->personal_purchase_count ?? 0),
                    'total_score' => array_sum(array_column($leaderScores, 'score')),
                    'unit_value_stockist' => $unitStockist,
                    'unit_value_leader' => $unitLeader,
                    'finalized_at' => now(),
                    'config_snapshot' => json_encode([
                        'version' => $this->bonusConfig['version'] ?? 'v10.1',
                        'config' => $this->bonusConfig,
                    ]),
                ]
            );

            // 消费商池分红
            foreach ($stockists as $user) {
                $shares = $user->personal_purchase_count ?? 0;
                $amount = $shares * $unitStockist;
                $trxId = null;
                if ($amount > 0) {
                    $newBalance = $user->balance + $amount;
                    $user->balance = $newBalance;
                    $user->save();

                    $trx = Transaction::create([
                        'user_id' => $user->id,
                        'trx_type' => '+',
                        'amount' => $amount,
                        'charge' => 0,
                        'post_balance' => $newBalance,
                        'remark' => 'stockist_dividend',
                        'source_type' => 'quarterly_settlement',
                        'source_id' => $quarterKey,
                        'details' => json_encode(['quarter_key' => $quarterKey]),
                    ]);
                    $trxId = $trx->id;
                }

                DividendLog::create([
                    'quarter_key' => $quarterKey,
                    'user_id' => $user->id,
                    'pool_type' => 'stockist',
                    'shares' => $shares,
                    'score' => null,
                    'dividend_amount' => $amount,
                    'status' => $amount > 0 ? 'paid' : 'skipped',
                    'reason' => $amount > 0 ? null : 'no_share',
                    'trx_id' => $trxId,
                ]);
            }

            // 领导人池分红
            foreach ($leaders as $user) {
                $score = $leaderScores[$user->id]['score'] ?? 0;
                $amount = $score * $unitLeader;
                $trxId = null;
                if ($amount > 0) {
                    $newBalance = $user->balance + $amount;
                    $user->balance = $newBalance;
                    $user->save();

                    $trx = Transaction::create([
                        'user_id' => $user->id,
                        'trx_type' => '+',
                        'amount' => $amount,
                        'charge' => 0,
                        'post_balance' => $newBalance,
                        'remark' => 'leader_dividend',
                        'source_type' => 'quarterly_settlement',
                        'source_id' => $quarterKey,
                        'details' => json_encode(['quarter_key' => $quarterKey, 'score' => $score]),
                    ]);
                    $trxId = $trx->id;
                }

                DividendLog::create([
                    'quarter_key' => $quarterKey,
                    'user_id' => $user->id,
                    'pool_type' => 'leader',
                    'shares' => null,
                    'score' => $score,
                    'dividend_amount' => $amount,
                    'status' => $amount > 0 ? 'paid' : 'skipped',
                    'reason' => $amount > 0 ? null : 'no_score',
                    'trx_id' => $trxId,
                ]);
            }
        });

        return [
            'status' => 'success',
            'quarter_key' => $quarterKey,
            'total_pv' => $totalPV,
            'pool_stockist' => $poolStockist,
            'pool_leader' => $poolLeader,
        ];
    }

    /**
     * 获取用户指定周的对碰实发金额
     */
    private function getUserPairPaidForWeek(int $userId, string $weekKey): float
    {
        $summary = DB::table("weekly_settlement_user_summaries")
            ->where("week_key", $weekKey)
            ->where("user_id", $userId)
            ->first();
            
        return $summary ? ($summary->pair_paid ?? 0.0) : 0.0;
    }
    
    /**
     * 获取管理奖比例
     */
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
    
    /**
     * 获取用户下级列表（按代数分组）
     * 
     * @param User $user 用户
     * @param int $maxGeneration 最大代数
     * @return array 按代数分组的下级用户
     */
    private function getDownlinesByGeneration(User $user, int $maxGeneration = 5): array
    {
        $downlines = [];
        
        // 从第1代开始，直接获取用户的所有直接下级
        $currentLevel = User::where("ref_by", $user->id)->get();
        
        for ($generation = 1; $generation <= $maxGeneration; $generation++) {
            // 首先，将当前层的所有用户添加到downlines中（这是当前代数）
            foreach ($currentLevel as $userInGeneration) {
                $downlines[$generation][] = $userInGeneration;
            }
            
            // 然后，为下一代准备数据
            $nextLevel = [];
            foreach ($currentLevel as $parentUser) {
                // 获取该用户的所有直接下级（下一代）
                $directReferrals = User::where("ref_by", $parentUser->id)->get();
                foreach ($directReferrals as $referral) {
                    $nextLevel[] = $referral;
                }
            }
            
            // 如果没有更多下级，提前结束
            if (empty($nextLevel)) {
                break;
            }
            
            $currentLevel = $nextLevel;
        }
        
        return $downlines;
    }
    
    /**
     * 计算管理奖（供calculateUserBonuses调用）
     * 
     * 基于下级对碰潜力，按代数计算（1-3代10%，4-5代5%）
     * 
     * @param int $userId 用户ID
     * @param array $allUserSummaries 所有用户的汇总数据
     * @param string $weekKey 周键
     * @return float 管理奖潜力金额
     */
    private function calculateMatchingBonusForUser(int $userId, array $allUserSummaries, string $weekKey): float
    {
        $totalMatching = 0.0;
        $user = User::find($userId);
        
        // 获取用户所有下级（1-5代）
        $downlines = $this->getDownlinesByGeneration($user, 5);
        
        foreach ($downlines as $generation => $users) {
            foreach ($users as $downlineUser) {
                // 从allUserSummaries中获取下级用户的对碰潜力
                foreach ($allUserSummaries as $summary) {
                    if ($summary['user_id'] == $downlineUser->id) {
                        // 使用封顶后的对碰潜力来计算管理奖潜力
                        $pairPotential = $summary['pair_capped_potential'];
                        
                        if ($pairPotential > 0) {
                            $rate = $this->getManagementBonusRate($generation);
                            $matching = $pairPotential * $rate;
                            $totalMatching += $matching;
                        }
                        break;
                    }
                }
            }
        }
        
        return $totalMatching;
    }
    
    /**
     * 计算管理奖实发（基于对碰实发金额）
     * 
     * 管理奖应该基于下级实际获得的对碰奖（封顶后×K），而不是封顶前潜力
     * 这确保了管理奖与对碰奖的K折扣一致
     * 
     * @param int $userId 用户ID
     * @param array $allUserSummaries 所有用户的汇总数据
     * @param float $kFactor K值因子
     * @return float 管理奖实发金额
     */
    private function calculateMatchingBonusPaid(int $userId, array $allUserSummaries, float $kFactor): float
    {
        $totalMatching = 0.0;
        $user = User::find($userId);
        
        // 获取用户所有下级（1-5代）
        $downlines = $this->getDownlinesByGeneration($user, 5);
        
        foreach ($downlines as $generation => $users) {
            foreach ($users as $downlineUser) {
                // 从allUserSummaries中获取下级用户的对碰实发金额
                foreach ($allUserSummaries as $summary) {
                    if ($summary['user_id'] == $downlineUser->id) {
                        // 使用对碰实发金额（pair_capped × K）来计算管理奖
                        $pairPaid = $summary['pair_paid'] ?? 0;
                        
                        if ($pairPaid > 0) {
                            $rate = $this->getManagementBonusRate($generation);
                            $matching = $pairPaid * $rate;
                            $totalMatching += $matching;
                        }
                        break;
                    }
                }
            }
        }
        
        return $totalMatching;
    }
    
    /**
     * 计算管理奖（基于实际对碰实发，供调试使用）
     * 
     * @param User $user 用户
     * @param string $weekKey 周键
     * @return float 管理奖金额
     */
    private function calculateMatchingBonus(User $user, string $weekKey): float
    {
        $totalMatching = 0.0;
        
        // 获取用户所有下级（1-5代）
        $downlines = $this->getDownlinesByGeneration($user, 5);
        
        foreach ($downlines as $generation => $users) {
            // 获取该代的下级用户列表
            foreach ($users as $downlineUser) {
                // 获取该下级用户在指定周的对碰实发金额
                $pairPaid = $this->getUserPairPaidForWeek($downlineUser->id, $weekKey);
                
                if ($pairPaid > 0) {
                    // 根据代数计算管理奖比例
                    $rate = $this->getManagementBonusRate($generation);
                    $matching = $pairPaid * $rate;
                    $totalMatching += $matching;
                }
            }
        }
        
        return $totalMatching;
    }

    /**
     * 结算历史
     */
    public function getSettlementHistory(int $page = 1, int $perPage = 20)
    {
        return WeeklySettlement::orderBy('start_date', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * K值详情
     */
    public function getKFactorDetails(string $weekKey): array
    {
        $record = WeeklySettlement::where('week_key', $weekKey)->first();
        if (!$record) {
            return ['status' => 'error', 'message' => 'not_found'];
        }

        return [
            'status' => 'success',
            'week_key' => $weekKey,
            'total_pv' => $record->total_pv,
            'fixed_sales' => $record->fixed_sales,
            'global_reserve' => $record->global_reserve,
            'variable_potential' => $record->variable_potential,
            'k_factor' => $record->k_factor,
        ];
    }

    private function getQuarterDateRange(string $quarterKey): array
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $quarterKey, $m)) {
            $year = (int)$m[1];
            $q = (int)$m[2];
            $startMonth = ($q - 1) * 3 + 1;
            $start = now()->setDate($year, $startMonth, 1)->startOfDay();
            $end = (clone $start)->addMonths(3)->subDay()->endOfDay();
            return [$start, $end];
        }
        $start = now()->startOfQuarter();
        $end = now()->endOfQuarter();
        return [$start, $end];
    }

    private function getQuarterWeakPV(int $userId, $start, $end): float
    {
        // 支持正负业绩混合计算
        $left = PvLedger::where('user_id', $userId)
            ->where('position', 1)
            ->whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
        $right = PvLedger::where('user_id', $userId)
            ->where('position', 2)
            ->whereIn('source_type', ['order', 'adjustment'])
            ->whereBetween('created_at', [$start, $end])
            ->sum(DB::raw('CASE WHEN trx_type = "+" THEN amount ELSE -ABS(amount) END'));
        return min($left, $right);
    }

    /**
     * 处理 PV 结转
     * 
     * 根据系统设置，在结算后扣除已发放的 PV
     * 
     * @param string $weekKey 周键
     * @param array $userSummaries 用户结算汇总
     */
    private function processCarryFlash(string $weekKey, array $userSummaries): void
    {
        // 获取结转设置
        $carryFlashMode = $this->getCarryFlashMode();
        
        // 如果不结转，直接返回
        if ($carryFlashMode === self::CARRY_FLASH_DISABLED) {
            return;
        }

        $this->initServices();

        $lockKey = "weekly_carry_flash:{$weekKey}";
        if (!$this->acquireLock($lockKey, 300)) {
            return;
        }

        try {
            $carryDone = DB::table('weekly_settlements')
                ->where('week_key', $weekKey)
                ->value('carry_flash_at');
            if ($carryDone) {
                return;
            }

            foreach ($userSummaries as $summary) {
                $userId = $summary['user_id'];
                $pairPaidActual = (float) ($summary['pair_paid_actual'] ?? 0);
                $weakPV = (float) ($summary['weak_pv'] ?? 0);
                $leftPV = (float) ($summary['left_pv'] ?? 0);
                $rightPV = (float) ($summary['right_pv'] ?? 0);
                $leftEnd = $leftPV;
                $rightEnd = $rightPV;
                
                switch ($carryFlashMode) {
                    case self::CARRY_FLASH_DEDUCT_PAID:
                        // 模式1：扣除已发放 PV
                        // 发放了多少对碰奖，就从左右区同时扣除相应比例的 PV
                        $capAmount = (float) ($summary['cap_amount'] ?? 0);
                        $capPV = (float) ($summary['cap_pv'] ?? 0);
                        $weakPosition = $leftPV <= $rightPV ? 1 : 2;
                        if ($pairPaidActual > 0 && $weakPV > 0 && $this->pairUnitAmount > 0) {
                            // 计算需要扣除的 PV 金额
                            // 假设每 3000 PV 发放 300 元，则每元对应 10 PV
                            $deductPV = $pairPaidActual * ($this->pairPvUnit / $this->pairUnitAmount);
                            $deductPV = min($deductPV, $weakPV);
                            
                            if ($deductPV > 0) {
                                $leftEnd = $leftPV - $deductPV;
                                $rightEnd = $rightPV - $deductPV;
                                $this->pvService->creditCarryFlash(
                                    $userId,
                                    1,
                                    $deductPV,
                                    $weekKey,
                                    "结转-扣除已发放PV({$pairPaidActual})",
                                    "carry_flash_deduct_paid"
                                );
                                $this->pvService->creditCarryFlash(
                                    $userId,
                                    2,
                                    $deductPV,
                                    $weekKey,
                                    "结转-扣除已发放PV({$pairPaidActual})",
                                    "carry_flash_deduct_paid"
                                );
                            }
                        }
                        // 若触发封顶，弱区超出封顶的 PV 归零
                        if ($capAmount > 0 && $this->pairUnitAmount > 0 && $weakPV > $capPV) {
                            $excessWeakPV = $weakPV - $capPV;
                            if ($excessWeakPV > 0) {
                                if ($weakPosition === 1) {
                                    $leftEnd -= $excessWeakPV;
                                } else {
                                    $rightEnd -= $excessWeakPV;
                                }
                                $this->pvService->creditCarryFlash(
                                    $userId,
                                    $weakPosition,
                                    $excessWeakPV,
                                    $weekKey,
                                    "结转-封顶超额PV",
                                    "carry_flash_cap_excess"
                                );
                            }
                        }
                        break;
                        
                    case self::CARRY_FLASH_DEDUCT_WEAK:
                        // 模式2：扣除弱区全部 PV
                        if ($weakPV > 0) {
                            $position = $leftPV <= $rightPV ? 1 : 2;
                            if ($position === 1) {
                                $leftEnd = $leftPV - $weakPV;
                            } else {
                                $rightEnd = $rightPV - $weakPV;
                            }
                            $this->pvService->creditCarryFlash(
                                $userId,
                                $position,
                                $weakPV,
                                $weekKey,
                                "结转-扣除弱区PV",
                                "carry_flash_deduct_weak"
                            );
                        }
                        break;
                        
                    case self::CARRY_FLASH_FLUSH_ALL:
                        // 模式3：清空全部 PV
                        if ($leftPV != 0 || $rightPV != 0) {
                            // 左区清零
                            if ($leftPV != 0) {
                                $leftEnd = 0.0;
                                $trxType = $leftPV > 0 ? '-' : '+';
                                $this->pvService->creditCarryFlash(
                                    $userId,
                                    1,
                                    abs($leftPV),
                                    $weekKey,
                                    "结转-清空左区PV",
                                    "carry_flash_flush_all",
                                    $trxType
                                );
                            }
                            // 右区清零
                            if ($rightPV != 0) {
                                $rightEnd = 0.0;
                                $trxType = $rightPV > 0 ? '-' : '+';
                                $this->pvService->creditCarryFlash(
                                    $userId,
                                    2,
                                    abs($rightPV),
                                    $weekKey,
                                    "结转-清空右区PV",
                                    "carry_flash_flush_all",
                                    $trxType
                                );
                            }
                        }
                        break;
                }

                if ($leftEnd != $leftPV || $rightEnd != $rightPV) {
                    DB::table('weekly_settlement_user_summaries')
                        ->where('week_key', $weekKey)
                        ->where('user_id', $userId)
                        ->update([
                            'left_pv_end' => $leftEnd,
                            'right_pv_end' => $rightEnd,
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::table('weekly_settlements')
                ->where('week_key', $weekKey)
                ->update([
                    'carry_flash_at' => now(),
                    'updated_at' => now(),
                ]);
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    /**
     * 获取结转模式设置
     * 
     * @return int 结转模式
     */
    private function getCarryFlashMode(): int
    {
        // 从 GeneralSetting 表获取设置
        $setting = GeneralSetting::first();
        if ($setting && isset($setting->cary_flash)) {
            return (int) $setting->cary_flash;
        }
        
        // 默认不结转
        return self::CARRY_FLASH_DISABLED;
    }
}
