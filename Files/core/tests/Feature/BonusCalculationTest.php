<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\BvLog;
use App\Constants\Status;
use App\Services\BonusCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * 奖金计算功能测试
 *
 * 测试直推、层碰、对碰、管理、加权等5个核心奖金计算模块
 */
class BonusCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected BonusCalculationService $bonusService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bonusService = new BonusCalculationService();
    }

    /** @test */
    public function it_can_calculate_direct_referral_bonus()
    {
        // 创建推荐人和被推荐人
        $referrer = User::factory()->create([
            'username' => 'referrer',
        ]);

        $referredUser = User::factory()->create([
            'ref_by' => $referrer->id,
        ]);

        // 创建订单
        $order = Order::factory()->create([
            'user_id' => $referredUser->id,
            'amount' => 100.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        // 计算直推奖金
        $bonus = $this->bonusService->calculateDirectReferralBonus($order);

        $this->assertIsNumeric($bonus);
        $this->assertGreaterThan(0, $bonus);

        // 验证交易记录
        $this->assertDatabaseHas('transactions', [
            'user_id' => $referrer->id,
            'amount' => $bonus,
            'trx_type' => '+',
            'details' => 'Direct referral bonus',
        ]);
    }

    /** @test */
    public function it_can_calculate_level_bonus()
    {
        // 创建多层级的推荐关系
        $level1 = User::factory()->create(['username' => 'level1']);
        $level2 = User::factory()->create(['ref_by' => $level1->id, 'username' => 'level2']);
        $level3 = User::factory()->create(['ref_by' => $level2->id, 'username' => 'level3']);

        $buyer = User::factory()->create(['ref_by' => $level3->id]);

        $order = Order::factory()->create([
            'user_id' => $buyer->id,
            'amount' => 200.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        // 计算层级奖金
        $bonusData = $this->bonusService->calculateLevelBonus($order);

        $this->assertIsArray($bonusData);

        // 验证各层级奖金
        foreach ($bonusData as $data) {
            $this->assertArrayHasKey('user_id', $data);
            $this->assertArrayHasKey('amount', $data);
            $this->assertArrayHasKey('level', $data);
            $this->assertGreaterThan(0, $data['amount']);
        }
    }

    /** @test */
    public function it_can_calculate_binary_bonus()
    {
        // 创建二叉树结构
        $parent = User::factory()->create(['username' => 'parent']);
        $leftChild = User::factory()->create([
            'ref_by' => $parent->id,
            'position' => Status::LEFT,
        ]);
        $rightChild = User::factory()->create([
            'ref_by' => $parent->id,
            'position' => Status::RIGHT,
        ]);

        // 在左侧创建订单
        $leftOrder = Order::factory()->create([
            'user_id' => $leftChild->id,
            'amount' => 150.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        // 在右侧创建订单
        $rightOrder = Order::factory()->create([
            'user_id' => $rightChild->id,
            'amount' => 200.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        // 计算二叉树奖金
        $bonus = $this->bonusService->calculateBinaryBonus($parent);

        $this->assertIsNumeric($bonus);
        $this->assertGreaterThanOrEqual(0, $bonus);
    }

    /** @test */
    public function it_can_calculate_management_bonus()
    {
        // 创建管理层级
        $topManager = User::factory()->create(['username' => 'top_manager']);
        $manager1 = User::factory()->create([
            'ref_by' => $topManager->id,
            'username' => 'manager1',
        ]);
        $manager2 = User::factory()->create([
            'ref_by' => $topManager->id,
            'username' => 'manager2',
        ]);

        // 模拟管理奖金计算
        $bonus = $this->bonusService->calculateManagementBonus($topManager);

        $this->assertIsNumeric($bonus);
        $this->assertGreaterThanOrEqual(0, $bonus);
    }

    /** @test */
    public function it_can_calculate_weighted_bonus()
    {
        // 创建加权分配结构
        $topUser = User::factory()->create(['username' => 'top_user']);

        // 创建多个下级用户
        User::factory()->count(5)->create([
            'ref_by' => $topUser->id,
        ]);

        $bonus = $this->bonusService->calculateWeightedBonus($topUser);

        $this->assertIsNumeric($bonus);
        $this->assertGreaterThanOrEqual(0, $bonus);
    }

    /** @test */
    public function it_applies_k_value_risk_control()
    {
        // 测试K值风控熔断机制
        $user = User::factory()->create();

        // 模拟大量奖金计算
        $totalBonus = $this->bonusService->calculateTotalBonusWithKControl($user);

        $this->assertIsNumeric($totalBonus);

        // 验证K值控制逻辑
        $this->assertLessThanOrEqual(
            $this->bonusService->getKValueLimit(),
            $totalBonus
        );
    }

    /** @test */
    public function it_records_bv_logs_correctly()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
        ]);

        $this->bonusService->processOrder($order);

        $this->assertDatabaseHas('bv_logs', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'purchase',
        ]);
    }

    /** @test */
    public function it_updates_user_binary_counts()
    {
        $parent = User::factory()->create();
        $leftChild = User::factory()->create([
            'ref_by' => $parent->id,
            'position' => Status::LEFT,
        ]);
        $rightChild = User::factory()->create([
            'ref_by' => $parent->id,
            'position' => Status::RIGHT,
        ]);

        $order = Order::factory()->create([
            'user_id' => $leftChild->id,
            'amount' => 100.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        $this->bonusService->processOrder($order);

        $parent->refresh();
        $this->assertGreaterThan(0, $parent->total_binary_left);
    }

    /** @test */
    public function it_prevents_duplicate_bonus_calculation()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        // 第一次计算
        $bonus1 = $this->bonusService->processOrder($order);

        // 第二次计算（应该被阻止）
        $bonus2 = $this->bonusService->processOrder($order);

        // 验证订单只被处理一次
        $this->assertEquals($bonus1, $bonus2);
        $this->assertEquals(1, Transaction::where('order_id', $order->id)->count());
    }

    /** @test */
    public function it_handles_cancelled_orders()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'status' => Status::ORDER_CANCELED,
        ]);

        $bonus = $this->bonusService->processOrder($order);

        // 取消的订单不应该产生奖金
        $this->assertEquals(0, $bonus);
    }

    /** @test */
    public function it_calculates_weekly_settlement()
    {
        $user = User::factory()->create();

        // 创建一周的订单
        Order::factory()->count(5)->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(3),
            'status' => Status::ORDER_COMPLETED,
        ]);

        $weeklyBonus = $this->bonusService->calculateWeeklySettlement($user);

        $this->assertIsNumeric($weeklyBonus);
        $this->assertGreaterThanOrEqual(0, $weeklyBonus);
    }

    /** @test */
    public function it_calculates_monthly_settlement()
    {
        $user = User::factory()->create();

        // 创建一月的订单
        Order::factory()->count(10)->create([
            'user_id' => $user->id,
            'created_at' => now()->subWeeks(2),
            'status' => Status::ORDER_COMPLETED,
        ]);

        $monthlyBonus = $this->bonusService->calculateMonthlySettlement($user);

        $this->assertIsNumeric($monthlyBonus);
        $this->assertGreaterThanOrEqual(0, $monthlyBonus);
    }

    /** @test */
    public function it_generates_correct_transaction_details()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'amount' => 100.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        $this->bonusService->processOrder($order);

        $transaction = Transaction::where('user_id', $user->id)->first();

        $this->assertNotNull($transaction);
        $this->assertStringContains('Bonus', $transaction->details);
    }

    /** @test */
    public function it_validates_bonus_calculation_accuracy()
    {
        // 测试奖金计算的准确性
        $referrer = User::factory()->create();
        $referredUser = User::factory()->create([
            'ref_by' => $referrer->id,
        ]);

        $order = Order::factory()->create([
            'user_id' => $referredUser->id,
            'amount' => 1000.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        $expectedBonus = $order->amount * 0.10; // 10%直推奖金
        $actualBonus = $this->bonusService->calculateDirectReferralBonus($order);

        $this->assertEquals($expectedBonus, $actualBonus);
    }

    /** @test */
    public function it_handles_large_volume_bonus_calculation()
    {
        // 测试大量订单的奖金计算性能
        $referrer = User::factory()->create();
        User::factory()->count(100)->create([
            'ref_by' => $referrer->id,
        ]);

        $startTime = microtime(true);

        Order::factory()->count(100)->create([
            'status' => Status::ORDER_COMPLETED,
        ]);

        $this->bonusService->processAllOrders();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // 应该在合理时间内完成
        $this->assertLessThan(10, $executionTime);
    }

    /** @test */
    public function it_applies_tax_deductions()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'amount' => 1000.00,
            'status' => Status::ORDER_COMPLETED,
        ]);

        $grossBonus = $this->bonusService->calculateDirectReferralBonus($order);
        $netBonus = $this->bonusService->applyTaxDeductions($grossBonus);

        $this->assertLessThanOrEqual($grossBonus, $netBonus);
    }

    /** @test */
    public function it_handles_currency_conversion()
    {
        $user = User::factory()->create([
            'currency' => 'USD',
        ]);

        $bonus = $this->bonusService->calculateBonusInCurrency($user, 1000, 'EUR');

        $this->assertIsNumeric($bonus);
        $this->assertGreaterThan(0, $bonus);
    }

    /** @test */
    public function it_generates_bonus_report()
    {
        $user = User::factory()->create();

        Order::factory()->count(5)->create([
            'user_id' => $user->id,
            'status' => Status::ORDER_COMPLETED,
        ]);

        $report = $this->bonusService->generateBonusReport($user);

        $this->assertArrayHasKey('direct_referral_bonus', $report);
        $this->assertArrayHasKey('level_bonus', $report);
        $this->assertArrayHasKey('binary_bonus', $report);
        $this->assertArrayHasKey('total_bonus', $report);
    }
}
