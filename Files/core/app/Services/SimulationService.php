<?php

namespace App\Services;

use App\Constants\Status;
use App\Models\Order;
use App\Models\PendingBonus;
use App\Models\Product;
use App\Models\PvLedger;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserExtra;
use App\Models\WeeklySettlement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimulationService
{
    private ?string $simPasswordHash = null;

    public function simulate(array $options): array
    {
        $persist = (bool) ($options['persist'] ?? false);
        $engine = (string) ($options['engine'] ?? '');
        $engine = $engine !== '' ? strtolower($engine) : ($persist ? 'db' : 'fast');

        if ($engine === 'fast') {
            return $this->simulateFast($options);
        }

        return $this->simulateDatabase($options);
    }

    private function simulateDatabase(array $options): array
    {
        $weeks = (int) ($options['weeks'] ?? 4);
        $leftRatio = max(0, min(100, (int) ($options['left_ratio'] ?? 60)));
        $ordersPerWeek = (int) ($options['orders_per_week'] ?? 200);
        $persist = (bool) ($options['persist'] ?? false);
        $settlementDryRun = (bool) ($options['settlement_dry_run'] ?? false);
        $ignoreLock = (bool) ($options['ignore_lock'] ?? false);
        $unpaidPerWeek = max(0, (int) ($options['unpaid_new_per_week'] ?? 0));
        [$totalUsersTarget, $weeklyNew] = $this->resolveUserTargets($options, $weeks, $ordersPerWeek);

        $settlementService = app(SettlementService::class);
        $results = [
            'weeks' => [],
            'months' => [],
            'quarters' => [],
            'quarter' => null, // backward compatible
            'summary' => null,
            'total_users' => 0,
        ];
        $monthAgg = [];
        $quarterKeys = [];

        $startBase = Carbon::now()->startOfWeek()->subWeeks(max(0, $weeks - 1));

        DB::beginTransaction();
        try {
            $shipmentService = app(OrderShipmentService::class);

            // 准备商品
            $product = Product::first();
            if (!$product) {
                $product = Product::create([
                    'name' => 'Simulated Lotus',
                    'price' => 3000,
                    'bv' => 3000,
                    'quantity' => 999999,
                    'status' => Status::ENABLE,
                ]);
            }

            // 初始化根用户
            $users = [];
            $children = [];
            $root = $this->createUser([
                'ref_by' => 0,
                'pos_id' => 0,
                'position' => 0,
                'username' => 'sim_root_' . Str::random(5),
                'plan_id' => 1,
                'rank_level' => 1,
            ]);
            $users[] = $root->id;
            $children[$root->id] = ['left' => false, 'right' => false];
            $buyerPool = [$root->id];

            // 周循环
            for ($w = 0; $w < $weeks; $w++) {
                $weekStart = (clone $startBase)->addWeeks($w);
                $weekEnd = (clone $weekStart)->endOfWeek();
                Carbon::setTestNow($weekStart->copy()->addDays(1)); // 锁定周内时间

                // 新增用户
                $newUsersAdded = 0;
                $limit = min($weeklyNew, $totalUsersTarget - count($users));
                $unpaidRemaining = min($unpaidPerWeek, $limit);
                for ($i = 0; $i < $limit; $i++) {
                    $preferLeft = random_int(1, 100) <= $leftRatio;
                    [$parentId, $position] = $this->findPlacement($children, $preferLeft);
                    $isUnpaid = $unpaidRemaining > 0;
                    if ($isUnpaid) {
                        $unpaidRemaining--;
                    }
                    $user = $this->createUser([
                        'ref_by' => $parentId,
                        'pos_id' => $parentId,
                        'position' => $position,
                        'username' => 'sim_' . Str::random(6),
                        'plan_id' => $isUnpaid ? 0 : 1,
                        'rank_level' => $isUnpaid ? 0 : 1,
                    ]);
                    $users[] = $user->id;
                    if (!$isUnpaid) {
                        $buyerPool[] = $user->id;
                    }
                    $children[$user->id] = ['left' => false, 'right' => false];
                    $children[$parentId][$position == Status::LEFT ? 'left' : 'right'] = true;
                    $newUsersAdded++;
                    if (count($users) >= $totalUsersTarget) {
                        break;
                    }
                }

                // 创建订单并发货
                $orderPV = 0.0;
                for ($i = 0; $i < $ordersPerWeek; $i++) {
                    $pool = !empty($buyerPool) ? $buyerPool : $users;
                    $buyerId = $pool[array_rand($pool)];
                    $orderDate = (clone $weekStart)->addDays(random_int(0, 6))->setTime(10, 0);
                    Carbon::setTestNow($orderDate);
                    $price = (float) ($product->price ?? 3000);
                    $quantity = 1;
                    $total = $price * $quantity;
                    $order = Order::create([
                        'user_id' => $buyerId,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'price' => $price,
                        'total_price' => $total,
                        'amount' => $total,
                        'trx' => 'SIM' . $weekStart->format('Ymd') . sprintf('%04d', $w * 1000 + $i),
                        'status' => Status::ORDER_PENDING,
                        'created_at' => $orderDate,
                        'updated_at' => $orderDate,
                    ]);
                    $orderPV += (float) ($order->total_price ?? $total);
                    $shipmentService->ship($order);
                }

                // 周结算
                Carbon::setTestNow((clone $weekEnd)->setTime(23, 0));
                $weekKey = $weekStart->format('o-\WW');
                DB::table('weekly_settlement_user_summaries')->where('week_key', $weekKey)->delete();
                DB::table('weekly_settlements')->where('week_key', $weekKey)->delete();
                $settlementService->executeWeeklySettlement($weekKey, $settlementDryRun, $ignoreLock);

                // 汇总本周
                $ws = WeeklySettlement::where('week_key', $weekKey)->first();
                $totalPv = $ws ? (float) $ws->total_pv : $orderPV;
                $systemPv = (float) PvLedger::whereBetween('created_at', [$weekStart, $weekEnd])
                    ->where('source_type', 'order')
                    ->where('trx_type', '+')
                    ->sum('amount');
                $fixedSales = (float) ($ws->fixed_sales ?? 0);
                $globalReserve = (float) ($ws->global_reserve ?? 0);
                $variablePotential = (float) ($ws->variable_potential ?? 0);
                $totalCap = $totalPv * 0.7;
                $remaining = $totalCap - $globalReserve - $fixedSales;
                $weekResult = [
                    'week_key' => $weekKey,
                    'new_users' => $newUsersAdded,
                    'total_users' => count($users),
                    'orders' => $ordersPerWeek,
                    'order_pv' => $orderPV,

                    'total_pv' => $totalPv,
                    'system_pv' => $systemPv,
                    'total_cap' => $totalCap,
                    'global_reserve' => $globalReserve,
                    'fixed_sales' => $fixedSales,
                    'variable_potential' => $variablePotential,
                    'remaining' => $remaining,
                    'k_factor' => (float) ($ws->k_factor ?? 0),
                    'direct_paid' => $this->sumTrx('direct_bonus', $weekStart, $weekEnd),
                    'level_pair_paid' => $this->sumTrx('level_pair_bonus', $weekStart, $weekEnd),
                    'pair_paid' => $this->sumTrx('pair_bonus', $weekStart, $weekEnd),
                    'matching_paid' => $this->sumTrx('matching_bonus', $weekStart, $weekEnd),
                    'pending_count' => PendingBonus::where('accrued_week_key', $weekKey)->count(),
                    'pending_amount' => (float) PendingBonus::where('accrued_week_key', $weekKey)->sum('amount'),
                ];
                $results['weeks'][] = $weekResult;

                // 月度聚合：以周结算时间（weekEnd）归属月份
                $monthKey = $weekEnd->format('Y-m');
                if (!isset($monthAgg[$monthKey])) {
                    $monthAgg[$monthKey] = [
                        'month_key' => $monthKey,
                        'weeks_count' => 0,
                        'new_users' => 0,
                        'total_users' => 0,
                        'orders' => 0,
                        'total_pv' => 0,
                        'system_pv' => 0,
                        'total_cap' => 0,
                        'global_reserve' => 0,
                        'fixed_sales' => 0,
                        'variable_potential' => 0,
                        'remaining' => 0,
                        'k_factor_avg' => 0,
                        '_k_sum' => 0,
                        'direct_paid' => 0,
                        'level_pair_paid' => 0,
                        'pair_paid' => 0,
                        'matching_paid' => 0,
                        'pending_count' => 0,
                        'pending_amount' => 0,
                    ];
                }
                $monthAgg[$monthKey]['weeks_count'] += 1;
                $monthAgg[$monthKey]['new_users'] += (int) ($weekResult['new_users'] ?? 0);
                $monthAgg[$monthKey]['total_users'] = (int) ($weekResult['total_users'] ?? 0);
                $monthAgg[$monthKey]['orders'] += (int) ($weekResult['orders'] ?? 0);
                $monthAgg[$monthKey]['total_pv'] += (float) ($weekResult['total_pv'] ?? 0);
                $monthAgg[$monthKey]['system_pv'] += (float) ($weekResult['system_pv'] ?? 0);
                $monthAgg[$monthKey]['total_cap'] += (float) ($weekResult['total_cap'] ?? 0);
                $monthAgg[$monthKey]['global_reserve'] += (float) ($weekResult['global_reserve'] ?? 0);
                $monthAgg[$monthKey]['fixed_sales'] += (float) ($weekResult['fixed_sales'] ?? 0);
                $monthAgg[$monthKey]['variable_potential'] += (float) ($weekResult['variable_potential'] ?? 0);
                $monthAgg[$monthKey]['remaining'] += (float) ($weekResult['remaining'] ?? 0);
                $monthAgg[$monthKey]['_k_sum'] += (float) ($weekResult['k_factor'] ?? 0);
                $monthAgg[$monthKey]['direct_paid'] += (float) ($weekResult['direct_paid'] ?? 0);
                $monthAgg[$monthKey]['level_pair_paid'] += (float) ($weekResult['level_pair_paid'] ?? 0);
                $monthAgg[$monthKey]['pair_paid'] += (float) ($weekResult['pair_paid'] ?? 0);
                $monthAgg[$monthKey]['matching_paid'] += (float) ($weekResult['matching_paid'] ?? 0);
                $monthAgg[$monthKey]['pending_count'] += (int) ($weekResult['pending_count'] ?? 0);
                $monthAgg[$monthKey]['pending_amount'] += (float) ($weekResult['pending_amount'] ?? 0);

                // 记录涉及的季度（按周结算时间归属季度）
                $qKey = $weekEnd->format('Y') . '-Q' . $weekEnd->quarter;
                $quarterKeys[$qKey] = true;
            }

            // 写入月度结果
            ksort($monthAgg);
            foreach ($monthAgg as &$row) {
                $weeksCount = max(1, (int) ($row['weeks_count'] ?? 1));
                $row['k_factor_avg'] = (float) ($row['_k_sum'] ?? 0) / $weeksCount;
                unset($row['_k_sum']);
            }
            unset($row);
            $results['months'] = array_values($monthAgg);

            // 季度分红（覆盖本次模拟涉及的全部季度）
            $quarterList = array_keys($quarterKeys);
            sort($quarterList);
            foreach ($quarterList as $quarterKey) {
                DB::table('dividend_logs')->where('quarter_key', $quarterKey)->delete();
                DB::table('quarterly_settlements')->where('quarter_key', $quarterKey)->delete();
                $results['quarters'][] = $settlementService->executeQuarterlySettlement($quarterKey, $settlementDryRun);
            }
            $results['quarter'] = $results['quarters'][count($results['quarters']) - 1] ?? null;

            $results['total_users'] = count($users);
            $kSum = array_sum(array_column($results['weeks'], 'k_factor'));
            $kAvg = $weeks > 0 ? ($kSum / $weeks) : 0;
            $results['summary'] = [
                'weeks' => $weeks,
                'months' => count($results['months']),
                'quarters' => count($results['quarters']),
                'order_pv' => array_sum(array_column($results['weeks'], 'order_pv')),
                'total_pv' => array_sum(array_column($results['weeks'], 'total_pv')),
                'system_pv' => array_sum(array_column($results['weeks'], 'system_pv')),
                'total_cap' => array_sum(array_column($results['weeks'], 'total_cap')),
                'global_reserve' => array_sum(array_column($results['weeks'], 'global_reserve')),
                'fixed_sales' => array_sum(array_column($results['weeks'], 'fixed_sales')),
                'variable_potential' => array_sum(array_column($results['weeks'], 'variable_potential')),
                'remaining' => array_sum(array_column($results['weeks'], 'remaining')),
                'k_factor_avg' => $kAvg,
                'direct_paid' => array_sum(array_column($results['weeks'], 'direct_paid')),
                'level_pair_paid' => array_sum(array_column($results['weeks'], 'level_pair_paid')),
                'pair_paid' => array_sum(array_column($results['weeks'], 'pair_paid')),
                'matching_paid' => array_sum(array_column($results['weeks'], 'matching_paid')),
                'pending_count' => array_sum(array_column($results['weeks'], 'pending_count')),
                'pending_amount' => array_sum(array_column($results['weeks'], 'pending_amount')),
            ];

            if ($persist) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } finally {
            Carbon::setTestNow();
        }

        return $results;
    }

    /**
     * 快速模拟：纯内存计算（不落库，不依赖 DB/队列），避免大参数导致 nginx/php-fpm 超时。
     */
    private function simulateFast(array $options): array
    {
        $weeks = max(1, (int) ($options['weeks'] ?? 4));
        $leftRatio = max(0, min(100, (int) ($options['left_ratio'] ?? 60)));
        $ordersPerWeek = max(0, (int) ($options['orders_per_week'] ?? 200));
        $unpaidPerWeek = max(0, (int) ($options['unpaid_new_per_week'] ?? 0));
        [$totalUsersTarget, $weeklyNew] = $this->resolveUserTargets($options, $weeks, $ordersPerWeek);

        $bonusConfig = app(BonusConfigService::class)->get();
        $pvUnit = 3000.0;
        $pairPvUnit = 3000.0;
        $directRate = (float) ($bonusConfig['direct_rate'] ?? 0.20);
        $levelPairRate = (float) ($bonusConfig['level_pair_rate'] ?? 0.25);
        $pairRate = (float) ($bonusConfig['pair_rate'] ?? 0.10);
        $pairUnitAmount = $pairPvUnit * $pairRate;
        $globalReserveRate = (float) ($bonusConfig['global_reserve_rate'] ?? 0.04);

        $managementRate = function (int $generation) use ($bonusConfig): float {
            $rates = (array) ($bonusConfig['management_rates'] ?? []);
            foreach ($rates as $range => $rate) {
                if (!is_numeric($rate)) {
                    continue;
                }
                $rangeStr = (string) $range;
                if (str_contains($rangeStr, '-')) {
                    [$start, $end] = array_map('intval', explode('-', $rangeStr));
                    if ($generation >= $start && $generation <= $end) {
                        return (float) $rate;
                    }
                } elseif (is_numeric($rangeStr) && (int) $rangeStr === $generation) {
                    return (float) $rate;
                }
            }
            return 0.0;
        };

        $weeklyCap = function (int $rank) use ($bonusConfig): float {
            $caps = (array) ($bonusConfig['pair_cap'] ?? []);
            return isset($caps[$rank]) ? (float) $caps[$rank] : 0.0;
        };

        $isActivated = function (int $userId, array $rankLevel): bool {
            return (int) ($rankLevel[$userId] ?? 0) >= 1;
        };

        $resolveRankLevel = function (int $personalPurchaseCount): int {
            if ($personalPurchaseCount >= 10) {
                return 3;
            }
            if ($personalPurchaseCount >= 3) {
                return 2;
            }
            if ($personalPurchaseCount >= 1) {
                return 1;
            }
            return 0;
        };

        // 粗略保护：避免极端参数把 php-fpm 卡死
        $estimatedDepth = max(1, (int) floor(log(max(2, $totalUsersTarget), 2)));
        $estimatedOps = $weeks * $ordersPerWeek * $estimatedDepth;
        if ($estimatedOps > 2_000_000) {
            throw new \RuntimeException("模拟规模过大（估算操作数 {$estimatedOps}），请降低 weeks/orders 或改用分批模拟。");
        }

        $results = [
            'weeks' => [],
            'months' => [],
            'quarters' => [],
            'quarter' => null,
            'summary' => null,
            'total_users' => 0,
        ];

        $startBase = Carbon::now()->startOfWeek()->subWeeks(max(0, $weeks - 1));

        // 用户/结构数据（1 为根）
        $nextUserId = 1;
        $userIds = [1];
        $refBy = [1 => 0];
        $posId = [1 => 0];
        $position = [1 => 0];
        $rankLevel = [1 => 1];
        $purchaseCount = [1 => 0];
        $buyerIds = [1];

        $bvLeft = [1 => 0.0];
        $bvRight = [1 => 0.0];

        $children = [1 => ['left' => false, 'right' => false]];

        $levelHitBits = []; // [uplineId][level] => bitmask (1=left,2=right,4=rewarded)

        $monthAgg = [];
        $quarterAgg = [];

        for ($w = 0; $w < $weeks; $w++) {
            $weekStart = (clone $startBase)->addWeeks($w);
            $weekEnd = (clone $weekStart)->endOfWeek();
            $weekKey = $weekStart->format('o-\WW');

            // 新增用户
            $newUsersAdded = 0;
            $limit = min($weeklyNew, $totalUsersTarget - count($userIds));
            $unpaidRemaining = min($unpaidPerWeek, $limit);
            for ($i = 0; $i < $limit; $i++) {
                $preferLeft = random_int(1, 100) <= $leftRatio;
                [$parentId, $pos] = $this->findPlacement($children, $preferLeft);

                $nextUserId++;
                $newId = $nextUserId;
                $userIds[] = $newId;
                $newUsersAdded++;
                $isUnpaid = $unpaidRemaining > 0;
                if ($isUnpaid) {
                    $unpaidRemaining--;
                }

                $refBy[$newId] = $parentId;
                $posId[$newId] = $parentId;
                $position[$newId] = $pos;
                $rankLevel[$newId] = $isUnpaid ? 0 : 1;
                $purchaseCount[$newId] = 0;
                $bvLeft[$newId] = 0.0;
                $bvRight[$newId] = 0.0;
                if (!$isUnpaid) {
                    $buyerIds[] = $newId;
                }
                $children[$newId] = ['left' => false, 'right' => false];
                $children[$parentId][$pos == Status::LEFT ? 'left' : 'right'] = true;

                if (count($userIds) >= $totalUsersTarget) {
                    break;
                }
            }

            $systemPV = 0.0;
            $orderPV = 0.0;
            $directPaid = 0.0;
            $directPending = 0.0;
            $levelPairPaid = 0.0;
            $levelPairPending = 0.0;
            $pendingCount = 0;
            $pendingAmount = 0.0;

            // 订单/发货（纯计算）
            $userCount = count($userIds);
            for ($i = 0; $i < $ordersPerWeek; $i++) {
                $buyerPool = !empty($buyerIds) ? $buyerIds : $userIds;
                $buyerId = $buyerPool[random_int(0, count($buyerPool) - 1)];
                $orderPV += $pvUnit;

                // 自购激活/升级
                $purchaseCount[$buyerId] = (int) ($purchaseCount[$buyerId] ?? 0) + 1;
                $rankLevel[$buyerId] = max((int) ($rankLevel[$buyerId] ?? 0), $resolveRankLevel($purchaseCount[$buyerId]));

                // 直推
                $sponsorId = (int) ($refBy[$buyerId] ?? 0);
                if ($sponsorId > 0) {
                    $amount = $pvUnit * $directRate;
                    if ($isActivated($sponsorId, $rankLevel)) {
                        $directPaid += $amount;
                    } else {
                        $directPending += $amount;
                        $pendingCount++;
                        $pendingAmount += $amount;
                    }
                }

                // 沿安置链PV入账 + 层碰
                $current = $buyerId;
                $level = 0;
                while (!empty($posId[$current])) {
                    $parent = (int) $posId[$current];
                    if ($parent <= 0) {
                        break;
                    }
                    $level++;
                    $side = (int) ($position[$current] ?? Status::LEFT);

                    if ($side === Status::LEFT) {
                        $bvLeft[$parent] = (float) ($bvLeft[$parent] ?? 0) + $pvUnit;
                    } else {
                        $bvRight[$parent] = (float) ($bvRight[$parent] ?? 0) + $pvUnit;
                    }
                    $systemPV += $pvUnit;

                    $bits = (int) ($levelHitBits[$parent][$level] ?? 0);
                    $bits |= ($side === Status::LEFT) ? 1 : 2;
                    if (($bits & 3) === 3 && ($bits & 4) === 0) {
                        $lpAmount = $pvUnit * $levelPairRate;
                        if ($isActivated($parent, $rankLevel)) {
                            $levelPairPaid += $lpAmount;
                        } else {
                            $levelPairPending += $lpAmount;
                            $pendingCount++;
                            $pendingAmount += $lpAmount;
                        }
                        $bits |= 4;
                    }
                    $levelHitBits[$parent][$level] = $bits;

                    $current = $parent;
                }
            }

            // 周结算（对碰/管理）
            $pairCapped = [];
            $matchingPotential = [];
            $pairCappedSum = 0.0;
            $matchingPotentialSum = 0.0;

            foreach ($userIds as $uid) {
                $left = (float) ($bvLeft[$uid] ?? 0);
                $right = (float) ($bvRight[$uid] ?? 0);
                $weak = min($left, $right);
                $pairCount = $pairPvUnit > 0 ? floor($weak / $pairPvUnit) : 0;
                $pairPotential = $pairCount * $pairUnitAmount;
                $cap = $weeklyCap((int) ($rankLevel[$uid] ?? 0));
                $capped = min($pairPotential, $cap);
                if ($capped > 0) {
                    $pairCapped[$uid] = $capped;
                    $pairCappedSum += $capped;
                }
                $matchingPotential[$uid] = 0.0;
            }

            // 管理奖：由下往上分摊（1-5代）
            foreach ($pairCapped as $downlineId => $potential) {
                $sponsor = (int) ($refBy[$downlineId] ?? 0);
                for ($gen = 1; $gen <= 5; $gen++) {
                    if ($sponsor <= 0) {
                        break;
                    }
                    $rate = $managementRate($gen);
                    if ($rate > 0) {
                        $matchingPotential[$sponsor] = (float) ($matchingPotential[$sponsor] ?? 0) + ($potential * $rate);
                    }
                    $sponsor = (int) ($refBy[$sponsor] ?? 0);
                }
            }

            foreach ($matchingPotential as $uid => $mp) {
                if ($mp > 0) {
                    $matchingPotentialSum += $mp;
                }
            }

            $fixedSales = $directPaid + $levelPairPaid + $directPending + $levelPairPending;
            $globalReserve = $orderPV * $globalReserveRate;
            $variablePotential = $pairCappedSum + $matchingPotentialSum;
            $totalCap = $orderPV * 0.7;
            $remaining = $totalCap - $globalReserve - $fixedSales;

            if ($remaining >= $variablePotential) {
                $kFactor = 1.0;
            } elseif ($remaining > 0 && $variablePotential > 0) {
                $kFactor = round($remaining / $variablePotential, 6);
            } else {
                $kFactor = 0.0;
            }

            $pairPaidTotal = 0.0;
            $matchingPaidTotal = 0.0;

            foreach ($userIds as $uid) {
                $pairPaid = (float) (($pairCapped[$uid] ?? 0) * $kFactor);
                $matchingPaid = (float) (($matchingPotential[$uid] ?? 0) * $kFactor);

                if ($pairPaid > 0) {
                    if ($isActivated($uid, $rankLevel)) {
                        $pairPaidTotal += $pairPaid;
                    } else {
                        $pendingCount++;
                        $pendingAmount += $pairPaid;
                    }
                }
                if ($matchingPaid > 0) {
                    if ($isActivated($uid, $rankLevel)) {
                        $matchingPaidTotal += $matchingPaid;
                    } else {
                        $pendingCount++;
                        $pendingAmount += $matchingPaid;
                    }
                }

                // PV扣减（仅扣减已发放对碰的PV）
                if ($pairPaid > 0 && $isActivated($uid, $rankLevel) && $pairUnitAmount > 0) {
                    $pairsPaid = floor($pairPaid / $pairUnitAmount);
                    $deduct = $pairsPaid * $pairPvUnit;
                    if ($deduct > 0) {
                        $bvLeft[$uid] = max(0.0, (float) ($bvLeft[$uid] ?? 0) - min((float) ($bvLeft[$uid] ?? 0), $deduct));
                        $bvRight[$uid] = max(0.0, (float) ($bvRight[$uid] ?? 0) - min((float) ($bvRight[$uid] ?? 0), $deduct));
                    }
                }
            }

            $weekResult = [
                'week_key' => $weekKey,
                'new_users' => $newUsersAdded,
                'total_users' => count($userIds),
                'orders' => $ordersPerWeek,
                'order_pv' => $orderPV,
                'total_pv' => $orderPV,
                'system_pv' => $systemPV,
                'total_cap' => $totalCap,
                'global_reserve' => $globalReserve,
                'fixed_sales' => $fixedSales,
                'variable_potential' => $variablePotential,
                'remaining' => $remaining,
                'k_factor' => $kFactor,
                'direct_paid' => $directPaid,
                'level_pair_paid' => $levelPairPaid,
                'pair_paid' => $pairPaidTotal,
                'matching_paid' => $matchingPaidTotal,
                'pending_count' => $pendingCount,
                'pending_amount' => $pendingAmount,
            ];
            $results['weeks'][] = $weekResult;

            $monthKey = $weekEnd->format('Y-m');
            if (!isset($monthAgg[$monthKey])) {
                $monthAgg[$monthKey] = [
                    'month_key' => $monthKey,
                    'weeks_count' => 0,
                    'new_users' => 0,
                    'total_users' => 0,
                    'orders' => 0,
                    'total_pv' => 0,
                    'system_pv' => 0,
                    'total_cap' => 0,
                    'global_reserve' => 0,
                    'fixed_sales' => 0,
                    'variable_potential' => 0,
                    'remaining' => 0,
                    'k_factor_avg' => 0,
                    '_k_sum' => 0,
                    'direct_paid' => 0,
                    'level_pair_paid' => 0,
                    'pair_paid' => 0,
                    'matching_paid' => 0,
                    'pending_count' => 0,
                    'pending_amount' => 0,
                ];
            }
            $monthAgg[$monthKey]['weeks_count'] += 1;
            $monthAgg[$monthKey]['new_users'] += (int) ($weekResult['new_users'] ?? 0);
            $monthAgg[$monthKey]['total_users'] = (int) ($weekResult['total_users'] ?? 0);
            $monthAgg[$monthKey]['orders'] += (int) ($weekResult['orders'] ?? 0);
            $monthAgg[$monthKey]['total_pv'] += (float) ($weekResult['total_pv'] ?? 0);
            $monthAgg[$monthKey]['system_pv'] += (float) ($weekResult['system_pv'] ?? 0);
            $monthAgg[$monthKey]['total_cap'] += (float) ($weekResult['total_cap'] ?? 0);
            $monthAgg[$monthKey]['global_reserve'] += (float) ($weekResult['global_reserve'] ?? 0);
            $monthAgg[$monthKey]['fixed_sales'] += (float) ($weekResult['fixed_sales'] ?? 0);
            $monthAgg[$monthKey]['variable_potential'] += (float) ($weekResult['variable_potential'] ?? 0);
            $monthAgg[$monthKey]['remaining'] += (float) ($weekResult['remaining'] ?? 0);
            $monthAgg[$monthKey]['_k_sum'] += (float) ($weekResult['k_factor'] ?? 0);
            $monthAgg[$monthKey]['direct_paid'] += (float) ($weekResult['direct_paid'] ?? 0);
            $monthAgg[$monthKey]['level_pair_paid'] += (float) ($weekResult['level_pair_paid'] ?? 0);
            $monthAgg[$monthKey]['pair_paid'] += (float) ($weekResult['pair_paid'] ?? 0);
            $monthAgg[$monthKey]['matching_paid'] += (float) ($weekResult['matching_paid'] ?? 0);
            $monthAgg[$monthKey]['pending_count'] += (int) ($weekResult['pending_count'] ?? 0);
            $monthAgg[$monthKey]['pending_amount'] += (float) ($weekResult['pending_amount'] ?? 0);

            $quarterKey = $weekEnd->format('Y') . '-Q' . $weekEnd->quarter;
            $quarterAgg[$quarterKey] = (float) ($quarterAgg[$quarterKey] ?? 0) + (float) ($weekResult['total_pv'] ?? 0);
        }

        ksort($monthAgg);
        foreach ($monthAgg as &$row) {
            $weeksCount = max(1, (int) ($row['weeks_count'] ?? 1));
            $row['k_factor_avg'] = (float) ($row['_k_sum'] ?? 0) / $weeksCount;
            unset($row['_k_sum']);
        }
        unset($row);
        $results['months'] = array_values($monthAgg);

        ksort($quarterAgg);
        $poolStockistRate = (float) ($bonusConfig['pool_stockist_rate'] ?? 0.01);
        $poolLeaderRate = (float) ($bonusConfig['pool_leader_rate'] ?? 0.03);
        foreach ($quarterAgg as $qKey => $qTotalPv) {
            $results['quarters'][] = [
                'status' => 'success',
                'dry_run' => true,
                'quarter_key' => $qKey,
                'total_pv' => $qTotalPv,
                'pool_stockist' => $qTotalPv * $poolStockistRate,
                'pool_leader' => $qTotalPv * $poolLeaderRate,
            ];
        }
        $results['quarter'] = $results['quarters'][count($results['quarters']) - 1] ?? null;

        $results['total_users'] = count($userIds);
        $kSum = array_sum(array_column($results['weeks'], 'k_factor'));
        $kAvg = $weeks > 0 ? ($kSum / $weeks) : 0;
        $results['summary'] = [
            'weeks' => $weeks,
            'months' => count($results['months']),
            'quarters' => count($results['quarters']),
            'order_pv' => array_sum(array_column($results['weeks'], 'order_pv')),
            'total_pv' => array_sum(array_column($results['weeks'], 'total_pv')),
            'system_pv' => array_sum(array_column($results['weeks'], 'system_pv')),
            'total_cap' => array_sum(array_column($results['weeks'], 'total_cap')),
            'global_reserve' => array_sum(array_column($results['weeks'], 'global_reserve')),
            'fixed_sales' => array_sum(array_column($results['weeks'], 'fixed_sales')),
            'variable_potential' => array_sum(array_column($results['weeks'], 'variable_potential')),
            'remaining' => array_sum(array_column($results['weeks'], 'remaining')),
            'k_factor_avg' => $kAvg,
            'direct_paid' => array_sum(array_column($results['weeks'], 'direct_paid')),
            'level_pair_paid' => array_sum(array_column($results['weeks'], 'level_pair_paid')),
            'pair_paid' => array_sum(array_column($results['weeks'], 'pair_paid')),
            'matching_paid' => array_sum(array_column($results['weeks'], 'matching_paid')),
            'pending_count' => array_sum(array_column($results['weeks'], 'pending_count')),
            'pending_amount' => array_sum(array_column($results['weeks'], 'pending_amount')),
        ];

        return $results;
    }

    private function resolveUserTargets(array $options, int $weeks, int $ordersPerWeek): array
    {
        $totalUsersTarget = (int) ($options['total_users'] ?? 0);
        $weeklyNew = (int) ($options['weekly_new_users'] ?? 0);
        $ratio = (float) ($options['user_order_ratio'] ?? 0.9);

        $ratio = $ratio > 0 ? $ratio : 0.9;
        $weeks = max(1, $weeks);

        if ($weeklyNew <= 0) {
            $weeklyNew = (int) max(1, ceil($ordersPerWeek * $ratio));
        }

        if ($totalUsersTarget <= 0) {
            $totalUsersTarget = max(20, 1 + ($weeklyNew * $weeks));
        }

        return [$totalUsersTarget, $weeklyNew];
    }

    private function createUser(array $data): User
    {
        $position = $data['position'] ?? null;
        $rankLevel = $data['rank_level'] ?? null;
        unset($data['position']);
        unset($data['rank_level']);

        if ($this->simPasswordHash === null) {
            $this->simPasswordHash = bcrypt('password');
        }

        $user = User::create(array_merge([
            'plan_id' => 1,
            'firstname' => 'Sim',
            'lastname' => 'User',
            'email' => Str::random(10) . '@sim.test',
            'password' => $this->simPasswordHash,
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
            'balance' => 0,
        ], $data));

        if ($position !== null) {
            $user->position = $position;
        }
        if ($rankLevel !== null) {
            $user->rank_level = (int) $rankLevel;
        }
        if ($position !== null || $rankLevel !== null) {
            $user->save();
        }

        UserExtra::firstOrCreate(['user_id' => $user->id]);
        return $user;
    }

    private function findPlacement(array $children, bool $preferLeft, ?array $rootSide = null, ?int $desiredSide = null): array
    {
        if ($rootSide !== null && $desiredSide !== null) {
            foreach ($children as $userId => $slots) {
                if ($userId !== 1 && (int) ($rootSide[$userId] ?? 0) !== $desiredSide) {
                    continue;
                }
                if ($preferLeft && !$slots['left']) {
                    return [$userId, Status::LEFT];
                }
                if (!$preferLeft && !$slots['right']) {
                    return [$userId, Status::RIGHT];
                }
            }
            foreach ($children as $userId => $slots) {
                if ($userId !== 1 && (int) ($rootSide[$userId] ?? 0) !== $desiredSide) {
                    continue;
                }
                if (!$slots['left']) {
                    return [$userId, Status::LEFT];
                }
                if (!$slots['right']) {
                    return [$userId, Status::RIGHT];
                }
            }
        }
        foreach ($children as $userId => $slots) {
            if ($preferLeft && !$slots['left']) {
                return [$userId, Status::LEFT];
            }
            if (!$preferLeft && !$slots['right']) {
                return [$userId, Status::RIGHT];
            }
        }
        // fallback：找任意有空位的
        foreach ($children as $userId => $slots) {
            if (!$slots['left']) {
                return [$userId, Status::LEFT];
            }
            if (!$slots['right']) {
                return [$userId, Status::RIGHT];
            }
        }
        // 极端情况，挂在根左侧
        $firstId = array_key_first($children);
        return [$firstId, Status::LEFT];
    }

    private function sumTrx(string $remark, Carbon $start, Carbon $end): float
    {
        return Transaction::where('remark', $remark)
            ->whereBetween('created_at', [$start, $end])
            ->sum('amount');
    }
}
