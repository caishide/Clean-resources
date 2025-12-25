<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\PvLedger;
use App\Models\WeeklySettlement;
use App\Services\SettlementServiceWithStrategy;
use App\Services\PVLedgerService;
use App\Services\CarryFlash\CarryFlashContext;
use App\Services\CarryFlash\CarryFlashStrategyFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * 结算功能集成测试
 */
class SettlementFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private SettlementServiceWithStrategy $settlementService;
    private PVLedgerService $pvService;
    private CarryFlashContext $carryFlashContext;
    private CarryFlashStrategyFactory $strategyFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pvService = app(PVLedgerService::class);
        $this->carryFlashContext = app(CarryFlashContext::class);
        $this->strategyFactory = new CarryFlashStrategyFactory($this->pvService);

        $this->settlementService = new SettlementServiceWithStrategy(
            $this->pvService,
            $this->carryFlashContext,
            $this->strategyFactory
        );

        // 设置禁用结转策略用于测试
        Config::set('settlement.carry_flash_strategy', 'disabled');
    }

    /**
     * 测试完整的周结算流程
     */
    public function test_complete_weekly_settlement_flow(): void
    {
        // 1. 创建测试用户
        $users = User::factory()->count(5)->create(['status' => 1]);

        // 2. 为用户创建 PV 记录
        foreach ($users as $user) {
            PvLedger::factory()->create([
                'user_id' => $user->id,
                'position' => 1,
                'pv_amount' => rand(500, 1500),
                'trx_type' => '+',
                'source_type' => 'order',
                'source_id' => rand(1000, 9999),
            ]);

            PvLedger::factory()->create([
                'user_id' => $user->id,
                'position' => 2,
                'pv_amount' => rand(400, 1200),
                'trx_type' => '+',
                'source_type' => 'order',
                'source_id' => rand(1000, 9999),
            ]);
        }

        // 3. 执行周结算
        $weekKey = '2025-W01';
        $result = $this->settlementService->executeWeeklySettlement($weekKey);

        // 4. 验证结算结果
        $this->assertTrue($result['success']);
        $this->assertEquals($weekKey, $result['week_key']);
        $this->assertEquals(5, $result['users_processed']);
        $this->assertNotEmpty($result['settlement_records']);

        // 5. 验证数据库记录
        $settlementCount = WeeklySettlement::where('week_key', $weekKey)->count();
        $this->assertEquals(5, $settlementCount);

        // 6. 验证结算记录数据
        $settlement = WeeklySettlement::where('week_key', $weekKey)->first();
        $this->assertNotNull($settlement);
        $this->assertGreaterThan(0, $settlement->left_pv);
        $this->assertGreaterThan(0, $settlement->right_pv);
        $this->assertGreaterThan(0, $settlement->bonus_amount);
    }

    /**
     * 测试结算幂等性
     */
    public function test_settlement_idempotency(): void
    {
        // 创建测试用户和 PV
        $user = User::factory()->create(['status' => 1]);
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'pv_amount' => 1000,
        ]);

        $weekKey = '2025-W01';

        // 第一次结算
        $result1 = $this->settlementService->executeWeeklySettlement($weekKey);
        $this->assertTrue($result1['success']);

        // 第二次结算应该失败
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('已经结算过了');
        $this->settlementService->executeWeeklySettlement($weekKey);
    }

    /**
     * 测试结算事务回滚
     */
    public function test_settlement_transaction_rollback(): void
    {
        // 创建测试用户
        $user = User::factory()->create(['status' => 1]);
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'pv_amount' => 1000,
        ]);

        // 使用无效的周期键
        $invalidWeekKey = 'invalid-format';

        // 应该抛出异常
        $this->expectException(\InvalidArgumentException::class);
        $this->settlementService->executeWeeklySettlement($invalidWeekKey);

        // 验证没有创建结算记录
        $settlementCount = WeeklySettlement::count();
        $this->assertEquals(0, $settlementCount);
    }

    /**
     * 测试多用户并发结算
     */
    public function test_concurrent_settlement(): void
    {
        // 创建多个用户
        $users = User::factory()->count(10)->create(['status' => 1]);

        foreach ($users as $user) {
            PvLedger::factory()->create([
                'user_id' => $user->id,
                'position' => 1,
                'pv_amount' => rand(500, 1500),
            ]);
            PvLedger::factory()->create([
                'user_id' => $user->id,
                'position' => 2,
                'pv_amount' => rand(400, 1200),
            ]);
        }

        $weekKey = '2025-W02';

        // 执行结算
        $result = $this->settlementService->executeWeeklySettlement($weekKey);

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['users_processed']);

        // 验证所有用户都有结算记录
        $settlementCount = WeeklySettlement::where('week_key', $weekKey)->count();
        $this->assertEquals(10, $settlementCount);
    }

    /**
     * 测试零 PV 用户结算
     */
    public function test_zero_pv_users_settlement(): void
    {
        // 创建没有 PV 的用户
        User::factory()->count(3)->create(['status' => 1]);

        $weekKey = '2025-W03';

        // 执行结算
        $result = $this->settlementService->executeWeeklySettlement($weekKey);

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['users_processed']);

        // 验证结算记录
        $settlements = WeeklySettlement::where('week_key', $weekKey)->get();
        $this->assertEquals(3, $settlements->count());

        // 验证零 PV 用户的奖金为 0
        foreach ($settlements as $settlement) {
            $this->assertEquals(0, $settlement->bonus_amount);
        }
    }

    /**
     * 测试季度结算集成
     */
    public function test_quarterly_settlement_integration(): void
    {
        // 创建用户
        $user = User::factory()->create(['status' => 1]);

        // 创建多周的结算记录
        for ($week = 1; $week <= 12; $week++) {
            WeeklySettlement::factory()->create([
                'user_id' => $user->id,
                'week_key' => sprintf('2025-W%02d', $week),
                'left_pv' => rand(500, 1500),
                'right_pv' => rand(400, 1200),
                'weak_pv' => rand(400, 1200),
                'bonus_amount' => rand(40, 120),
                'settlement_date' => now()->addWeeks($week - 1),
            ]);
        }

        $quarterKey = '2025-Q1';

        // 执行季度结算
        $result = $this->settlementService->executeQuarterlySettlement($quarterKey);

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals($quarterKey, $result['quarter_key']);
        $this->assertEquals(1, $result['users_processed']);
        $this->assertNotEmpty($result['settlement_records']);
    }

    /**
     * 测试 PV 账本与结算集成
     */
    public function test_pv_ledger_settlement_integration(): void
    {
        // 创建用户
        $user = User::factory()->create(['status' => 1]);

        // 创建 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'pv_amount' => 1000,
            'trx_type' => '+',
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'pv_amount' => 800,
            'trx_type' => '+',
        ]);

        // 获取用户 PV 汇总
        $summary = $this->pvService->getUserPVSummary($user->id);

        $this->assertEquals(1000, $summary['left_pv']);
        $this->assertEquals(800, $summary['right_pv']);

        // 执行结算
        $weekKey = '2025-W04';
        $result = $this->settlementService->executeWeeklySettlement($weekKey);

        // 验证结算结果
        $this->assertTrue($result['success']);

        $settlement = WeeklySettlement::where('user_id', $user->id)
            ->where('week_key', $weekKey)
            ->first();

        $this->assertNotNull($settlement);
        $this->assertEquals(1000, $settlement->left_pv);
        $this->assertEquals(800, $settlement->right_pv);
        $this->assertEquals(800, $settlement->weak_pv);
        $this->assertEquals(80, $settlement->bonus_amount); // 800 * 0.1
    }

    /**
     * 测试结转策略集成
     */
    public function test_carry_flash_strategy_integration(): void
    {
        // 创建用户
        $user = User::factory()->create(['status' => 1]);

        // 创建 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'pv_amount' => 1000,
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'pv_amount' => 800,
        ]);

        // 设置扣除已结算 PV 策略
        Config::set('settlement.carry_flash_strategy', 'deduct_paid');

        // 执行结算
        $weekKey = '2025-W05';
        $result = $this->settlementService->executeWeeklySettlement($weekKey);

        // 验证结转记录
        $carryFlashEntries = PvLedger::where('user_id', $user->id)
            ->where('source_type', 'carry_flash_deduct_paid')
            ->get();

        $this->assertGreaterThan(0, $carryFlashEntries->count());
    }
}