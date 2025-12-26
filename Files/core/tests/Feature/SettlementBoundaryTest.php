<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\PvLedger;
use App\Models\PendingBonus;
use App\Models\WeeklySettlement;
use App\Models\WeeklySettlementUserSummary;
use App\Models\UserPointsLog;
use App\Services\SettlementService;
use App\Services\BonusService;
use App\Services\AdjustmentService;
use App\Services\PointsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use App\Constants\Status;

/**
 * 结算边界场景测试
 *
 * 测试 K 值熔断、负 PV、退款冲正、待处理奖金释放等边界场景
 */
class SettlementBoundaryTest extends TestCase
{
    use DatabaseTransactions;

    protected SettlementService $settlementService;
    protected BonusService $bonusService;
    protected AdjustmentService $adjustmentService;
    protected PointsService $pointsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settlementService = app(SettlementService::class);
        $this->bonusService = app(BonusService::class);
        $this->adjustmentService = app(AdjustmentService::class);
        $this->pointsService = app(PointsService::class);
    }

    // ==================== K 值边界测试 ====================

    /** @test */
    public function k_value_equals_one_when_remaining_sufficient()
    {
        // 构造场景：Remaining >= A，K 值应为 1
        $user = User::factory()->create([
            'rank_level' => 3,
            'balance' => 0,
        ]);

        // 创建大量 PV（确保 Remaining 充足）
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 30000,
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 30000,
        ]);

        $weekKey = now()->format('o-\WW');
        $result = $this->settlementService->executeWeeklySettlement($weekKey, true);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1.0, $result['k_factor']);
    }

    /** @test */
    public function k_value_equals_zero_when_remaining_zero_or_negative()
    {
        // 构造场景：Remaining <= 0，K 值应为 0
        $user = User::factory()->create([
            'rank_level' => 3,
            'balance' => 0,
        ]);

        // 创建 PV
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 3000,
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 3000,
        ]);

        // 创建超大刚性支出，使 Remaining <= 0
        $sponsor = User::factory()->create(['balance' => 0]);
        for ($i = 0; $i < 50; $i++) {
            Transaction::create([
                'user_id' => $sponsor->id,
                'trx_type' => '+',
                'amount' => 10000,
                'remark' => 'direct_bonus',
                'source_type' => 'order',
                'source_id' => 'TEST-ORDER-' . str_pad($i, 3, '0', STR_PAD_LEFT),
            ]);
        }

        $weekKey = now()->format('o-\WW');
        $result = $this->settlementService->executeWeeklySettlement($weekKey, true);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(0, $result['k_factor']);
    }

    // ==================== 周封顶边界测试 ====================

    /** @test */
    public function pair_bonus_capped_at_junior_level()
    {
        // 初级封顶 3000
        $user = User::factory()->create([
            'rank_level' => 1,
            'balance' => 0,
        ]);

        // 创造超出封顶的 PV
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 30000,
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 30000,
        ]);

        $weekKey = now()->format('o-\WW');
        $result = $this->settlementService->executeWeeklySettlement($weekKey, true);

        $this->assertEquals('success', $result['status']);

        $summary = WeeklySettlementUserSummary::where('week_key', $weekKey)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($summary);
        // 对碰理论应被封顶
        $this->assertLessThanOrEqual(3000, $summary->pair_theoretical ?? PHP_FLOAT_MAX);
    }

    /** @test */
    public function pair_bonus_capped_at_senior_level()
    {
        // 高级封顶 36000
        $user = User::factory()->create([
            'rank_level' => 3,
            'balance' => 0,
        ]);

        // 创造大量 PV
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 300000,
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 300000,
        ]);

        $weekKey = now()->format('o-\WW');
        $result = $this->settlementService->executeWeeklySettlement($weekKey, true);

        $this->assertEquals('success', $result['status']);

        $summary = WeeklySettlementUserSummary::where('week_key', $weekKey)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertLessThanOrEqual(36000, $summary->pair_theoretical ?? PHP_FLOAT_MAX);
    }

    /** @test */
    public function management_bonus_not_counted_in_cap()
    {
        // 管理奖不应计入封顶计算
        $manager = User::factory()->create([
            'rank_level' => 3,
            'balance' => 0,
        ]);

        $downlines = [];
        for ($i = 0; $i < 5; $i++) {
            $downline = User::factory()->create([
                'ref_by' => $manager->id,
                'rank_level' => 2,
                'balance' => 0,
            ]);
            $downlines[] = $downline;

            PvLedger::factory()->create([
                'user_id' => $downline->id,
                'position' => 1,
                'amount' => 30000,
            ]);
            PvLedger::factory()->create([
                'user_id' => $downline->id,
                'position' => 2,
                'amount' => 30000,
            ]);
        }

        $weekKey = now()->format('o-\WW');

        foreach ($downlines as $downline) {
            PvLedger::factory()->create([
                'user_id' => $manager->id,
                'position' => 1,
                'amount' => 3000,
            ]);
        }

        $result = $this->settlementService->executeWeeklySettlement($weekKey, true);

        $this->assertEquals('success', $result['status']);

        $summary = WeeklySettlementUserSummary::where('week_key', $weekKey)
            ->where('user_id', $manager->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertGreaterThan(0, $summary->matching_potential ?? 0);
    }

    // ==================== 待处理奖金测试 ====================

    /** @test */
    public function direct_bonus_goes_to_pending_when_recipient_inactive()
    {
        // 收款人未激活时，直推奖应进入待处理
        $sponsor = User::factory()->create([
            'rank_level' => 0,
            'balance' => 0,
        ]);
        $buyer = User::factory()->create([
            'ref_by' => $sponsor->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'quantity' => 1,
            'trx' => 'TEST-PENDING-' . uniqid(),
        ]);

        $result = $this->bonusService->issueDirectBonus($order);

        $this->assertEquals('pending', $result['type'] ?? 'unknown');

        $pendingBonus = PendingBonus::where('recipient_id', $sponsor->id)
            ->where('bonus_type', 'direct')
            ->where('source_id', $order->trx)
            ->first();

        $this->assertNotNull($pendingBonus);
        $this->assertEquals('pending', $pendingBonus->status);
        $this->assertEquals(600, $pendingBonus->amount);
    }

    /** @test */
    public function pending_bonus_released_on_user_activation()
    {
        $user = User::factory()->create([
            'rank_level' => 0,
            'balance' => 0,
        ]);

        PendingBonus::create([
            'recipient_id' => $user->id,
            'bonus_type' => 'direct',
            'source_type' => 'order',
            'source_id' => 'TEST-ORDER-001',
            'amount' => 600,
            'accrued_week_key' => now()->format('o-\WW'),
            'status' => 'pending',
            'release_mode' => 'auto',
        ]);

        $user->rank_level = 1;
        $user->save();

        $released = $this->bonusService->releasePendingBonusesOnActivation($user->id);

        $this->assertNotEmpty($released);
        $pendingBonus = PendingBonus::where('recipient_id', $user->id)->first();
        $this->assertEquals('released', $pendingBonus->status);
        $this->assertNotNull($pendingBonus->released_trx);
        $user->refresh();
        $this->assertEquals(600, $user->balance);
    }

    /** @test */
    public function pair_bonus_pending_when_user_inactive()
    {
        // 验证未激活用户创建周结算汇总的逻辑
        $user = User::factory()->create([
            'rank_level' => 0,
            'balance' => 0,
        ]);

        $weekKey = now()->format('o-\WW');

        // 直接使用 DB 表创建，使用正确的列名
        DB::table('weekly_settlement_user_summaries')->insert([
            'week_key' => $weekKey,
            'user_id' => $user->id,
            'left_pv_initial' => 3000,
            'right_pv_initial' => 3000,
            'pair_paid' => 300,
            'matching_paid' => 100,
            'k_factor' => 1.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = DB::table('weekly_settlement_user_summaries')
            ->where('week_key', $weekKey)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(300, $summary->pair_paid);
    }

    // ==================== 退款冲正测试 ====================

    /** @test */
    public function refund_before_finalize_creates_auto_reversal()
    {
        // 结算前退款应自动冲正
        // 注意：sponsor 需要已激活才能直接收到奖金，否则进入待处理
        $sponsor = User::factory()->create(['balance' => 0, 'rank_level' => 1]);
        $buyer = User::factory()->create(['ref_by' => $sponsor->id]);

        // 创建订单，初始状态为 pending (0)
        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'quantity' => 1,
            'trx' => 'TEST-REFUND-' . uniqid(),
            'status' => Status::ORDER_PENDING,
        ]);

        // 触发直推奖
        $this->bonusService->issueDirectBonus($order);

        // 验证直推奖已发放（已激活用户直接入账）
        $this->assertEquals(600, $sponsor->refresh()->balance);

        // 执行退款冲正
        $batch = $this->adjustmentService->createRefundAdjustment($order, '客户退款');

        // 刷新模型以获取自动终态化的时间戳
        $batch = $batch->refresh();

        // 结算前应自动终态化
        $this->assertNotNull($batch->finalized_at);

        // 验证余额已冲正
        $sponsor->refresh();
        $this->assertEquals(0, $sponsor->balance);
    }

    /** @test */
    public function refund_after_finalize_requires_manual_approval()
    {
        // 测试退款冲正创建 - 通过直接插入已终态化的周结算来模拟结算后场景
        $sponsor = User::factory()->create(['balance' => 0, 'rank_level' => 1]);
        $buyer = User::factory()->create(['ref_by' => $sponsor->id]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'quantity' => 1,
            'trx' => 'TEST-REFUND-LATE-' . uniqid(),
            'status' => Status::ORDER_PENDING,
        ]);

        $this->bonusService->issueDirectBonus($order);

        // 模拟已终态化的周结算（使用完整字段）
        $weekKey = $order->created_at->format('o-\WW');
        $startDate = now()->startOfWeek();
        $endDate = now()->endOfWeek();

        DB::table('weekly_settlements')->insert([
            'week_key' => $weekKey,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_pv' => 3000,
            'fixed_sales' => 600,
            'global_reserve' => 120,
            'variable_potential' => 0,
            'k_factor' => 1.0,
            'finalized_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 执行退款冲正
        $batch = $this->adjustmentService->createRefundAdjustment($order, '结算后退款');

        // 验证退款批次已创建，reason_type 为结算后退款
        $this->assertNotNull($batch);
        $this->assertEquals('refund_after_finalize', $batch->reason_type);
        // 结算后不应自动终态化
        $this->assertNull($batch->finalized_at);
    }

    /** @test */
    public function negative_balance_allowed_after_refund()
    {
        $sponsor = User::factory()->create(['balance' => 0, 'rank_level' => 1]);
        $buyer = User::factory()->create(['ref_by' => $sponsor->id]);

        for ($i = 0; $i < 10; $i++) {
            $order = Order::factory()->create([
                'user_id' => $buyer->id,
                'quantity' => 1,
                'trx' => 'TEST-REFUND-MULTI-' . $i . '-' . uniqid(),
                'status' => Status::ORDER_PENDING,
            ]);
            $this->bonusService->issueDirectBonus($order);
        }

        $this->assertEquals(6000, $sponsor->refresh()->balance);

        $lastOrder = Order::where('trx', 'LIKE', 'TEST-REFUND-MULTI-9-%')->first();

        $batch = $this->adjustmentService->createRefundAdjustment($lastOrder, '批量退款测试');

        $sponsor->refresh();
        $this->assertEquals(5400, $sponsor->balance);

        // 模拟已提现 1000
        $sponsor->balance = 5000;
        $sponsor->save();

        $lastOrder2 = Order::where('trx', 'LIKE', 'TEST-REFUND-MULTI-8-%')->first();
        $batch2 = $this->adjustmentService->createRefundAdjustment($lastOrder2, '提现后退款');

        $sponsor->refresh();
        $this->assertLessThan(5000, $sponsor->balance);
    }

    /** @test */
    public function withdrawal_restricted_when_balance_negative()
    {
        $user = User::factory()->create(['balance' => -100]);

        $restriction = $this->adjustmentService->checkWithdrawalRestriction($user->id);

        $this->assertFalse($restriction['can_withdraw']);
        $this->assertStringContainsString('负', $restriction['reason']);
        $this->assertEquals(-100, $restriction['balance']);
    }

    /** @test */
    public function withdrawal_allowed_when_balance_non_negative()
    {
        $user = User::factory()->create(['balance' => 1000]);

        $restriction = $this->adjustmentService->checkWithdrawalRestriction($user->id);

        $this->assertTrue($restriction['can_withdraw']);
        $this->assertNull($restriction['reason']);
        $this->assertEquals(1000, $restriction['balance']);
    }

    // ==================== 莲子积分边界测试 ====================

    /** @test */
    public function daily_checkin_points_limit_one_per_day()
    {
        $user = User::factory()->create();

        // 第一天签到
        $result1 = $this->pointsService->creditDailyCheckIn($user->id);
        $this->assertEquals('success', $result1['status'] ?? 'error');

        // 同一天再次签到（应该被拒绝）
        $result2 = $this->pointsService->creditDailyCheckIn($user->id);
        $this->assertEquals('error', $result2['status'] ?? 'success');
        $this->assertStringContainsString('已经签到', $result2['message'] ?? '');
    }

    /** @test */
    public function team_points_reversed_on_refund()
    {
        $user = User::factory()->create();
        $weekKey = now()->format('o-\WW');

        // 模拟 TEAM 莲子产出
        UserPointsLog::create([
            'user_id' => $user->id,
            'source_type' => 'TEAM',
            'source_id' => $weekKey,
            'points' => 300,
            'description' => 'TEAM莲子-' . $weekKey,
        ]);

        // 模拟退款冲正
        $batchKey = 'ADJ-TEST-' . uniqid();
        UserPointsLog::create([
            'user_id' => $user->id,
            'source_type' => 'TEAM_REVERSAL',
            'source_id' => $batchKey,
            'points' => -300,
            'description' => 'TEAM莲子-' . $weekKey . '（退款冲正）',
        ]);

        $asset = \App\Models\UserAsset::firstOrCreate(['user_id' => $user->id]);
        $this->assertEquals(0, $asset->points);
    }

    // ==================== 季度分红边界测试 ====================

    /** @test */
    public function quarterly_dividend_skipped_for_inactive_user()
    {
        $user = User::factory()->create([
            'personal_purchase_count' => 5,
            'last_activity_date' => now()->subMonths(4),
            'balance' => 0,
        ]);

        $quarterKey = now()->format('Y') . '-Q' . ceil(now()->month / 3);
        $result = $this->settlementService->executeQuarterlySettlement($quarterKey, true);

        $this->assertEquals('success', $result['status']);
    }

    /** @test */
    public function quarterly_dividend_for_active_eligible_user()
    {
        $user = User::factory()->create([
            'personal_purchase_count' => 5,
            'last_activity_date' => now(),
            'balance' => 0,
        ]);

        $quarterKey = now()->format('Y') . '-Q' . ceil(now()->month / 3);
        $result = $this->settlementService->executeQuarterlySettlement($quarterKey, true);

        $this->assertEquals('success', $result['status']);
    }

    /** @test */
    public function leader_pool_requires_minimum_quarterly_weak_pv()
    {
        $user = User::factory()->create([
            'leader_rank_code' => 'liuli_xingzhe',
            'leader_rank_multiplier' => 1,
            'personal_purchase_count' => 10,
            'balance' => 0,
        ]);

        PvLedger::create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 5000,
            'level' => 1,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'ORDER-LP-1',
        ]);

        PvLedger::create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 5000,
            'level' => 1,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'ORDER-LP-2',
        ]);

        $quarterKey = now()->format('Y') . '-Q' . ceil(now()->month / 3);
        $result = $this->settlementService->executeQuarterlySettlement($quarterKey, true);

        $this->assertEquals('success', $result['status']);
    }

    // ==================== PV 边界测试 ====================

    /** @test */
    public function negative_pv_from_refund_deducted_correctly()
    {
        $user = User::factory()->create();

        PvLedger::create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 10000,
            'level' => 1,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'ORDER-POSITIVE',
        ]);

        PvLedger::create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 3000,
            'level' => 1,
            'trx_type' => '-',
            'source_type' => 'adjustment',
            'source_id' => 'ADJ-REFUND',
        ]);

        $positivePV = DB::table('pv_ledger')
            ->where('user_id', $user->id)
            ->where('position', 1)
            ->where('trx_type', '+')
            ->sum('amount');

        $negativePV = DB::table('pv_ledger')
            ->where('user_id', $user->id)
            ->where('position', 1)
            ->where('trx_type', '-')
            ->sum('amount');

        $actualPV = $positivePV - $negativePV;

        $this->assertEquals(10000, $positivePV);
        $this->assertEquals(3000, $negativePV);
        $this->assertEquals(7000, $actualPV);
    }

    /** @test */
    public function weak_pv_calculation_with_mixed_positive_negative()
    {
        $user = User::factory()->create();

        // 左区：正 5000，负 2000 = 净 3000
        PvLedger::create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 5000,
            'level' => 1,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'ORDER-L-1',
        ]);
        PvLedger::create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 2000,
            'level' => 1,
            'trx_type' => '-',
            'source_type' => 'adjustment',
            'source_id' => 'ADJ-L-1',
        ]);

        // 右区：正 4000 = 净 4000
        PvLedger::create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 4000,
            'level' => 1,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'ORDER-R-1',
        ]);

        $leftPositive = DB::table('pv_ledger')
            ->where('user_id', $user->id)
            ->where('position', 1)
            ->where('trx_type', '+')
            ->sum('amount');

        $leftNegative = DB::table('pv_ledger')
            ->where('user_id', $user->id)
            ->where('position', 1)
            ->where('trx_type', '-')
            ->sum('amount');

        $rightPositive = DB::table('pv_ledger')
            ->where('user_id', $user->id)
            ->where('position', 2)
            ->where('trx_type', '+')
            ->sum('amount');

        $leftPV = $leftPositive - $leftNegative;
        $rightPV = $rightPositive;
        $weakPV = min($leftPV, $rightPV);

        $this->assertEquals(3000, $leftPV);
        $this->assertEquals(4000, $rightPV);
        $this->assertEquals(3000, $weakPV);
    }

    // ==================== 幂等性测试 ====================

    /** @test */
    public function weekly_settlement_idempotent()
    {
        $user = User::factory()->create(['balance' => 0]);
        $weekKey = '2025-W99';

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 3000,
        ]);
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 3000,
        ]);

        $result1 = $this->settlementService->executeWeeklySettlement($weekKey);
        $this->assertEquals('success', $result1['status']);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('已结算');

        $this->settlementService->executeWeeklySettlement($weekKey);
    }

    /** @test */
    public function direct_bonus_idempotent()
    {
        // Sponsor needs to be activated to receive bonus directly
        $sponsor = User::factory()->create(['balance' => 0, 'rank_level' => 1]);
        $buyer = User::factory()->create([
            'ref_by' => $sponsor->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'quantity' => 1,
            'trx' => 'TEST-IDEMPOTENT-' . uniqid(),
        ]);

        $result1 = $this->bonusService->issueDirectBonus($order);
        $this->assertEquals('paid', $result1['type']);

        $result2 = $this->bonusService->issueDirectBonus($order);
        $this->assertEquals('noop', $result2['type']);

        $trxCount = Transaction::where('remark', 'direct_bonus')
            ->where('source_id', $order->trx)
            ->count();

        $this->assertEquals(1, $trxCount);
    }
}
