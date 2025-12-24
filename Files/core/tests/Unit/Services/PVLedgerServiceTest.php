<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PVLedgerService;
use App\Models\User;
use App\Models\PvLedger;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;

/**
 * PV 台账服务单元测试
 * 
 * 测试覆盖：
 * - PV 累加功能
 * - PV 余额查询
 * - 周 PV 计算
 * - 幂等性保证
 */
class PVLedgerServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private PVLedgerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PVLedgerService::class);
    }

    /**
     * 测试 PV 累加功能
     */
    public function test_credit_pv_creates_ledger_entries(): void
    {
        // 创建测试用户
        $user = User::factory()->create([
            'pos_id' => null,
            'position' => 1,
        ]);

        // 创建测试订单
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'quantity' => 2,
            'trx' => 'TEST-' . time(),
        ]);

        // 执行 PV 累加
        $results = $this->service->creditPVFromOrder($order);

        // 验证结果
        $this->assertIsArray($results);
        $this->assertNotEmpty($results);

        // 验证数据库记录
        $pvEntries = PvLedger::where('source_type', 'order')
            ->where('source_id', $order->trx)
            ->get();

        $this->assertGreaterThan(0, $pvEntries->count());
    }

    /**
     * 测试 PV 累加的幂等性
     */
    public function test_credit_pv_is_idempotent(): void
    {
        $user = User::factory()->create([
            'pos_id' => null,
            'position' => 1,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'quantity' => 1,
            'trx' => 'IDEMPOTENT-' . time(),
        ]);

        // 第一次执行
        $results1 = $this->service->creditPVFromOrder($order);
        $count1 = PvLedger::where('source_id', $order->trx)->count();

        // 第二次执行（应该不会重复插入）
        $results2 = $this->service->creditPVFromOrder($order);
        $count2 = PvLedger::where('source_id', $order->trx)->count();

        // 验证幂等性
        $this->assertEquals($count1, $count2);
        $this->assertEquals($results1, $results2);
    }

    /**
     * 测试获取用户 PV 余额
     */
    public function test_get_user_pv_balance(): void
    {
        $user = User::factory()->create();

        // 创建测试 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 3000,
            'trx_type' => '+',
            'source_type' => 'order',
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 6000,
            'trx_type' => '+',
            'source_type' => 'order',
        ]);

        // 获取 PV 余额
        $balance = $this->service->getUserPVBalance($user->id);

        // 验证结果
        $this->assertIsArray($balance);
        $this->assertArrayHasKey('left_pv', $balance);
        $this->assertArrayHasKey('right_pv', $balance);
        $this->assertArrayHasKey('total_pv', $balance);
        $this->assertEquals(3000, $balance['left_pv']);
        $this->assertEquals(6000, $balance['right_pv']);
        $this->assertEquals(9000, $balance['total_pv']);
    }

    /**
     * 测试获取用户 PV 余额（包含负向记录）
     */
    public function test_get_user_pv_balance_with_negative_entries(): void
    {
        $user = User::factory()->create();

        // 创建正向记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 5000,
            'trx_type' => '+',
            'source_type' => 'order',
        ]);

        // 创建负向记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 2000,
            'trx_type' => '-',
            'source_type' => 'adjustment',
        ]);

        // 获取 PV 余额
        $balance = $this->service->getUserPVBalance($user->id);

        // 验证结果（5000 - 2000 = 3000）
        $this->assertEquals(3000, $balance['left_pv']);
    }

    /**
     * 测试获取本周新增弱区 PV
     */
    public function test_get_weekly_new_weak_pv(): void
    {
        $user = User::factory()->create();
        $weekKey = now()->format('o-\WW');

        // 创建本周 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 3000,
            'trx_type' => '+',
            'source_type' => 'order',
            'created_at' => now(),
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 6000,
            'trx_type' => '+',
            'source_type' => 'order',
            'created_at' => now(),
        ]);

        // 获取本周弱区 PV
        $weakPV = $this->service->getWeeklyNewWeakPV($user->id, $weekKey);

        // 验证结果（min(3000, 6000) = 3000）
        $this->assertEquals(3000, $weakPV);
    }

    /**
     * 测试获取用户 PV 汇总
     */
    public function test_get_user_pv_summary(): void
    {
        $user = User::factory()->create();

        // 创建历史 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 9000,
            'trx_type' => '+',
            'source_type' => 'order',
            'created_at' => now()->subDays(10),
        ]);

        // 创建本周 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 3000,
            'trx_type' => '+',
            'source_type' => 'order',
            'created_at' => now(),
        ]);

        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 2,
            'amount' => 6000,
            'trx_type' => '+',
            'source_type' => 'order',
            'created_at' => now(),
        ]);

        // 获取 PV 汇总
        $summary = $this->service->getUserPVSummary($user->id);

        // 验证结果
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('left_pv', $summary);
        $this->assertArrayHasKey('right_pv', $summary);
        $this->assertArrayHasKey('this_week_left', $summary);
        $this->assertArrayHasKey('this_week_right', $summary);
        
        // 总 PV = 9000 + 3000 = 12000
        $this->assertEquals(12000, $summary['left_pv']);
        // 本周左区 PV = 3000
        $this->assertEquals(3000, $summary['this_week_left']);
        // 本周右区 PV = 6000
        $this->assertEquals(6000, $summary['this_week_right']);
    }

    /**
     * 测试周结算扣减 PV
     */
    public function test_deduct_pv_for_weekly_settlement(): void
    {
        $user = User::factory()->create();
        $weekKey = now()->format('o-\WW');

        // 创建初始 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 6000,
            'trx_type' => '+',
            'source_type' => 'order',
        ]);

        // 扣减 PV
        $this->service->deductPVForWeeklySettlement($user->id, 3000, $weekKey);

        // 验证扣减记录
        $deductEntry = PvLedger::where('user_id', $user->id)
            ->where('source_type', 'weekly_settlement')
            ->where('source_id', $weekKey)
            ->first();

        $this->assertNotNull($deductEntry);
        $this->assertEquals(3000, $deductEntry->amount);
        $this->assertEquals('-', $deductEntry->trx_type);
    }

    /**
     * 测试结转 PV
     */
    public function test_credit_carry_flash(): void
    {
        $user = User::factory()->create();
        $weekKey = now()->format('o-\WW');

        // 执行结转
        $result = $this->service->creditCarryFlash(
            $user->id,
            1,
            1500,
            $weekKey,
            '测试结转'
        );

        // 验证结果
        $this->assertTrue($result);

        $carryEntry = PvLedger::where('user_id', $user->id)
            ->where('source_type', 'carry_flash')
            ->where('source_id', $weekKey)
            ->first();

        $this->assertNotNull($carryEntry);
        $this->assertEquals(1500, $carryEntry->amount);
    }

    /**
     * 测试安置链获取
     */
    public function test_get_placement_chain(): void
    {
        // 创建多层安置关系
        $root = User::factory()->create(['pos_id' => null]);
        $level1 = User::factory()->create(['pos_id' => $root->id, 'position' => 1]);
        $level2 = User::factory()->create(['pos_id' => $level1->id, 'position' => 2]);
        $level3 = User::factory()->create(['pos_id' => $level2->id, 'position' => 1]);

        // 创建订单
        $order = Order::factory()->create([
            'user_id' => $level3->id,
            'quantity' => 1,
            'trx' => 'CHAIN-' . time(),
        ]);

        // 执行 PV 累加
        $results = $this->service->creditPVFromOrder($order);

        // 验证安置链（应该有3个上级）
        $this->assertGreaterThanOrEqual(3, count($results));
    }

    /**
     * 测试不包含结转的 PV 余额
     */
    public function test_get_user_pv_balance_without_carry(): void
    {
        $user = User::factory()->create();

        // 创建正常 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 5000,
            'trx_type' => '+',
            'source_type' => 'order',
        ]);

        // 创建结转 PV 记录
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'amount' => 2000,
            'trx_type' => '-',
            'source_type' => 'carry_flash',
        ]);

        // 获取不包含结转的 PV 余额
        $balance = $this->service->getUserPVBalance($user->id, false);

        // 验证结果（应该不包含结转记录）
        $this->assertEquals(5000, $balance['left_pv']);
    }

    /**
     * 测试批量 PV 累加性能
     */
    public function test_bulk_credit_pv_performance(): void
    {
        $user = User::factory()->create(['pos_id' => null]);

        $startTime = microtime(true);

        // 创建多个订单
        for ($i = 0; $i < 10; $i++) {
            $order = Order::factory()->create([
                'user_id' => $user->id,
                'quantity' => 1,
                'trx' => 'BULK-' . $i . '-' . time(),
            ]);

            $this->service->creditPVFromOrder($order);
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // 转换为毫秒

        // 验证性能（应该在合理时间内完成）
        $this->assertLessThan(5000, $executionTime, '批量 PV 累加应该在5秒内完成');
    }
}