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
use App\Services\PVLedgerService;
use App\Services\PointsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SettlementService
{
    protected PVLedgerService $pvService;
    protected PointsService $pointsService;
    protected BonusConfigService $bonusConfigService;
    protected array $bonusConfig = [];
    protected float $pairPvUnit = 3000;
    protected float $pairUnitAmount = 300.0;

    public function __construct(BonusConfigService $bonusConfigService)
    {
        $this->pvService = app(PVLedgerService::class);
        $this->pointsService = app(PointsService::class);
        $this->bonusConfigService = $bonusConfigService;
        $this->bonusConfig = $bonusConfigService->get();
        $pairRate = $this->bonusConfig['pair_rate'] ?? 0.10;
        $this->pairUnitAmount = $this->pairPvUnit * $pairRate;
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
        // 分布式锁：防止同一week_key并发执行
        $lockKey = "weekly_settlement:{$weekKey}";
        if (!$ignoreLock && !$this->acquireLock($lockKey, 300)) { // 5分钟超时
            throw new \Exception("结算正在进行中，请稍后重试");
        }

        try {
            // Step 1: 统计本周刚性支出（FixedSales）
            $fixedSales = $this->calculateFixedSales($weekKey);

            // Step 2: 统计本周总PV
            $totalPV = $this->calculateTotalPV($weekKey);

            // Step 3: 功德池预留
            $globalReserveRate = $this->bonusConfig['global_reserve_rate'] ?? 0.04;
            $globalReserve = $totalPV * $globalReserveRate;

            // Step 4: 计算每用户对碰理论
            $userSummaries = $this->calculateUserBonuses($weekKey);

            // Step 4b: 计算管理奖（基于对碰潜力）
            foreach ($userSummaries as &$summary) {
                $summary['matching_potential'] = $this->calculateMatchingBonusForUser($summary['user_id'], $userSummaries, $weekKey);
            }
            unset($summary);

            // Step 5: 计算K值
            $kFactor = $this->calculateKFactor($totalPV, $globalReserve, $fixedSales, $userSummaries);

            // Step 6: 计算实发金额
            foreach ($userSummaries as &$summary) {
                $summary['pair_paid'] = $summary['pair_capped_potential'] * $kFactor;
                $summary['matching_paid'] = $summary['matching_potential'] * $kFactor;
            }
            unset($summary);

            if ($dryRun) {
                // 预演模式：不写入数据库
                $totalPairPaid = array_sum(array_column($userSummaries, 'pair_paid'));
                $totalMatchingPaid = array_sum(array_column($userSummaries, 'matching_paid'));
                return [
                    'status' => 'success',
                    'dry_run' => true,
                    'week_key' => $weekKey,
                    'total_pv' => $totalPV,
                    'fixed_sales' => $fixedSales,
                    'global_reserve' => $globalReserve,
                    'k_factor' => $kFactor,
                    'user_count' => count($userSummaries),
                    'total_pair_paid' => $totalPairPaid,
                    'total_matching_paid' => $totalMatchingPaid,
                    'variable_bonus_total' => $totalPairPaid + $totalMatchingPaid,
                    'bonus_breakdown' => [
                        '对碰奖' => ['amount' => $totalPairPaid, 'count' => count(array_filter($userSummaries, fn($s) => $s['pair_paid'] > 0))],
                        '管理奖' => ['amount' => $totalMatchingPaid, 'count' => count(array_filter($userSummaries, fn($s) => $s['matching_paid'] > 0))],
                    ]
                ];
            }

            // Step 7: 写入数据库并发放
            DB::transaction(function () use ($weekKey, $totalPV, $fixedSales, $globalReserve, $kFactor, $userSummaries) {
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
                foreach ($userSummaries as $summary) {
                    $user = User::find($summary['user_id']);
                    if (!$user) continue;

                    $userExtra = $user->userExtra ?? $user->userExtra()->create([]);
                    $initialLeft = $userExtra->bv_left ?? 0;
                    $initialRight = $userExtra->bv_right ?? 0;
                    $deductPV = 0;
                    $leftDeduct = 0;
                    $rightDeduct = 0;
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
                                'details' => '对碰奖-' . $weekKey,
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
                                'details' => '管理奖-' . $weekKey,
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

                    // PV扣减（仅扣减已发放对碰的PV，按左右各扣pairs）
                    $pairsPaid = $this->pairUnitAmount > 0
                        ? floor(($pairActuallyPaid ?? 0) / $this->pairUnitAmount)
                        : 0;
                    $deductPerSide = $pairsPaid * $this->pairPvUnit;
                    if ($deductPerSide > 0) {
                        // 左
                        DB::table('pv_ledger')->updateOrInsert(
                            [
                                'user_id' => $summary['user_id'],
                                'position' => 1,
                                'trx_type' => '-',
                                'source_type' => 'weekly_settlement',
                                'source_id' => $weekKey,
                            ],
                            [
                                'from_user_id' => null,
                                'level' => 0,
                                'amount' => -$deductPerSide,
                                'adjustment_batch_id' => null,
                                'reversal_of_id' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                        // 右
                        DB::table('pv_ledger')->updateOrInsert(
                            [
                                'user_id' => $summary['user_id'],
                                'position' => 2,
                                'trx_type' => '-',
                                'source_type' => 'weekly_settlement',
                                'source_id' => $weekKey,
                            ],
                            [
                                'from_user_id' => null,
                                'level' => 0,
                                'amount' => -$deductPerSide,
                                'adjustment_batch_id' => null,
                                'reversal_of_id' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );

                        // 扣减UserExtra的PV（左右独立扣减）
                        $userExtra = $user->userExtra;
                        if ($userExtra) {
                            $leftDeduct = min($userExtra->bv_left, $deductPerSide);
                            $rightDeduct = min($userExtra->bv_right, $deductPerSide);
                            DB::table('user_extras')->where('user_id', $user->id)->update([
                                'bv_left' => max(0, $userExtra->bv_left - $leftDeduct),
                                'bv_right' => max(0, $userExtra->bv_right - $rightDeduct)
                            ]);
                        }
                    }

                    // TEAM 莲子
                    $weakPVNew = $summary['weak_pv_new'] ?? 0;
                    if ($weakPVNew > 0) {
                        $this->pointsService->creditTeamPoints($summary['user_id'], $weakPVNew, $weekKey);
                    }

                    // 写入用户周结汇总
                    $weakPV = min($initialLeft, $initialRight);
                    $pairCount = $this->pairPvUnit > 0 ? floor($weakPV / $this->pairPvUnit) : 0;
                    DB::table('weekly_settlement_user_summaries')->updateOrInsert(
                        ['week_key' => $weekKey, 'user_id' => $summary['user_id']],
                        [
                            'left_pv_initial' => $initialLeft,
                            'right_pv_initial' => $initialRight,
                            'left_pv_end' => max(0, $initialLeft - $leftDeduct),
                            'right_pv_end' => max(0, $initialRight - $rightDeduct),
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
            });

            return [
                'status' => 'success',
                'week_key' => $weekKey,
                'total_pv' => $totalPV,
                'k_factor' => $kFactor,
                'user_count' => count($userSummaries)
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
        return PvLedger::where('source_type', 'order')
            ->whereBetween('created_at', [$start, $end])
            ->where('trx_type', '+')
            ->sum('amount');
    }

    /**
     * 计算每用户对碰/管理理论
     */
    private function calculateUserBonuses(string $weekKey): array
    {
        $users = User::active()->get();
        $userSummaries = [];

        foreach ($users as $user) {
            $userExtra = $user->userExtra;
            if (!$userExtra) continue;

            // 对碰计算
            $weakPV = min($userExtra->bv_left ?? 0, $userExtra->bv_right ?? 0);
            $pairCount = $this->pairPvUnit > 0 ? floor($weakPV / $this->pairPvUnit) : 0;
            $pairPotential = $pairCount * $this->pairUnitAmount;

            // 周封顶（仅对碰）
            $capAmount = $this->getWeeklyCapAmount($user);
            $pairCapped = min($pairPotential, $capAmount);

            // 管理奖（基于潜力，后续在 calculateMatchingBonusForUser 覆盖）
            $matchingPotential = 0;

            $weakPVNew = $this->pvService->getWeeklyNewWeakPV($user->id, $weekKey);

            $userSummaries[] = [
                'user_id' => $user->id,
                'pair_capped_potential' => $pairCapped,
                'matching_potential' => $matchingPotential,
                'cap_amount' => $capAmount,
                'weak_pv_new' => $weakPVNew,
            ];
        }

        return $userSummaries;
    }

    /**
     * 计算K值
     */
    private function calculateKFactor(float $totalPV, float $globalReserve, float $fixedSales, array $userSummaries): float
    {
        $totalCap = $totalPV * 0.7; // 70%总拨出
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
     * @return float 管理奖金额
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
                        // 使用对碰潜力来计算管理奖（在K值计算之前）
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
        if (preg_match('/^(\\d{4})-Q([1-4])$/', $quarterKey, $m)) {
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
        $left = PvLedger::where('user_id', $userId)
            ->where('position', 1)
            ->where('source_type', 'order')
            ->whereBetween('created_at', [$start, $end])
            ->where('trx_type', '+')
            ->sum('amount');
        $right = PvLedger::where('user_id', $userId)
            ->where('position', 2)
            ->where('source_type', 'order')
            ->whereBetween('created_at', [$start, $end])
            ->where('trx_type', '+')
            ->sum('amount');
        return min($left, $right);
    }
}
