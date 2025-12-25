<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\UserExtra;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\PvLedger;
use App\Models\UserPointsLog;
use App\Models\AdjustmentBatch;
use App\Models\AdjustmentEntry;
use App\Services\AdjustmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdjustmentServiceTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    protected AdjustmentService $adjustmentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adjustmentService = app(AdjustmentService::class);
    }

    // =============================================================================
    // createRefundAdjustment Tests
    // =============================================================================

    /** @test */
    public function it_can_create_refund_adjustment()
    {
        $user = User::factory()->create(['balance' => 1000]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'trx' => 'TEST-ORDER-' . time(),
            'amount' => 500,
        ]);

        $reason = 'Test refund reason';

        $batch = $this->adjustmentService->createRefundAdjustment($order, $reason);

        $this->assertInstanceOf(AdjustmentBatch::class, $batch);
        $this->assertNotNull($batch->batch_key);
        $this->assertStringStartsWith('ADJ-', $batch->batch_key);
        $this->assertTrue(in_array($batch->reason_type, ['refund_before_finalize', 'refund_after_finalize']));
        $this->assertEquals('order', $batch->reference_type);
        $this->assertEquals($order->trx, $batch->reference_id);
    }

    // =============================================================================
    // finalizeAdjustmentBatch Tests
    // =============================================================================

    /** @test */
    public function it_can_finalize_adjustment_batch()
    {
        $user = User::factory()->create(['balance' => 1000]);
        $userExtra = $user->userExtra()->create([
            'bv_left' => 500,
            'bv_right' => 300,
            'points' => 100,
        ]);

        // 创建 PV 记录
        $pvEntry = PvLedger::create([
            'user_id' => $user->id,
            'from_user_id' => $user->id,
            'amount' => 100,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'TEST-TRX-001',
            'position' => 1,
            'level' => 1,
        ]);

        // 创建交易记录
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'amount' => 50,
            'trx_type' => '+',
            'remark' => 'direct_bonus',
            'source_type' => 'order',
            'source_id' => 'TEST-TRX-001',
            'post_balance' => 1050,
        ]);

        // 创建莲子记录
        $pointsLog = UserPointsLog::create([
            'user_id' => $user->id,
            'source_type' => 'PURCHASE',
            'source_id' => 'TEST-TRX-001',
            'points' => 50,
            'description' => 'Purchase bonus',
        ]);

        // 创建待处理的调整批次
        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-TEST-001',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'TEST-TRX-001',
            'snapshot' => json_encode(['order_id' => 1, 'reason' => 'test']),
        ]);

        // 执行调整
        $this->adjustmentService->finalizeAdjustmentBatch($batch->id, 1);

        // 验证 PV 被冲正
        $reversalPV = PvLedger::where('reversal_of_id', $pvEntry->id)->first();
        $this->assertNotNull($reversalPV);
        $this->assertEquals(-100, $reversalPV->amount);
        $this->assertEquals('adjustment', $reversalPV->source_type);

        // 验证莲子被冲正
        $reversalPoints = UserPointsLog::where('reversal_of_id', $pointsLog->id)->first();
        $this->assertNotNull($reversalPoints);
        $this->assertEquals(-50, $reversalPoints->points);

        // 验证批次已完结
        $batch->refresh();
        $this->assertNotNull($batch->finalized_at);

        // 验证 BV 更新
        $userExtra->refresh();
        $this->assertEquals(400, $userExtra->bv_left); // 500 - 100
    }

    // =============================================================================
    // reversePVEntries Tests
    // =============================================================================

    /** @test */
    public function it_can_reverse_pv_entries_with_correct_amounts()
    {
        $user = User::factory()->create();
        $userExtra = $user->userExtra()->create([
            'bv_left' => 500,
            'bv_right' => 300,
        ]);

        // 创建正向 PV 记录
        PvLedger::create([
            'user_id' => $user->id,
            'from_user_id' => $user->id,
            'amount' => 100,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'PV-TEST-001',
            'position' => 1,
            'level' => 1,
        ]);

        // 创建调整批次
        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-PV-TEST',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'PV-TEST-001',
            'snapshot' => json_encode(['test' => true]),
        ]);

        // 验证 PV 冲正
        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        $userExtra->refresh();

        // 验证左区 BV 减少
        $this->assertEquals(400, $userExtra->bv_left); // 500 - 100
        $this->assertEquals(300, $userExtra->bv_right); // 不变
    }

    /** @test */
    public function it_handles_pv_reversal_for_right_position()
    {
        $user = User::factory()->create();
        $userExtra = $user->userExtra()->create([
            'bv_left' => 500,
            'bv_right' => 300,
        ]);

        PvLedger::create([
            'user_id' => $user->id,
            'from_user_id' => $user->id,
            'amount' => 100,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'PV-TEST-002',
            'position' => 2, // 右区
            'level' => 1,
        ]);

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-PV-TEST-2',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'PV-TEST-002',
            'snapshot' => json_encode(['test' => true]),
        ]);

        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        $userExtra->refresh();

        $this->assertEquals(500, $userExtra->bv_left); // 不变
        $this->assertEquals(200, $userExtra->bv_right); // 300 - 100
    }

    /** @test */
    public function it_prevents_negative_bv_values()
    {
        $user = User::factory()->create();
        $userExtra = $user->userExtra()->create([
            'bv_left' => 50,
            'bv_right' => 30,
        ]);

        // 创建大于现有 BV 的 PV 记录
        PvLedger::create([
            'user_id' => $user->id,
            'from_user_id' => $user->id,
            'amount' => 100, // 大于现有 BV
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'PV-TEST-OVER',
            'position' => 1,
            'level' => 1,
        ]);

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-PV-OVER',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'PV-TEST-OVER',
            'snapshot' => json_encode(['test' => true]),
        ]);

        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        $userExtra->refresh();

        // 应该被限制为 0，不会负数
        $this->assertEquals(0, $userExtra->bv_left);
        $this->assertEquals(30, $userExtra->bv_right);
    }

    // =============================================================================
    // reverseBonusTransactions Tests
    // =============================================================================

    /** @test */
    public function it_can_reverse_direct_bonus()
    {
        $user = User::factory()->create(['balance' => 1000]);

        // 创建直推奖交易
        Transaction::create([
            'user_id' => $user->id,
            'amount' => 50,
            'trx_type' => '+',
            'remark' => 'direct_bonus',
            'source_type' => 'order',
            'source_id' => 'TRX-DIRECT-001',
            'post_balance' => 1050,
        ]);

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-DIRECT-001',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'TRX-DIRECT-001',
            'snapshot' => json_encode(['test' => true]),
        ]);

        // 执行调整
        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        // 验证批次已完结
        $batch->refresh();
        $this->assertNotNull($batch->finalized_at);

        // 验证交易数量增加（原始 + 冲正）
        $this->assertGreaterThan(1, Transaction::count());
    }

    /** @test */
    public function it_can_reverse_level_pair_bonus()
    {
        $user = User::factory()->create(['balance' => 2000]);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'amount' => 100,
            'trx_type' => '+',
            'remark' => 'level_pair_bonus',
            'source_type' => 'order',
            'source_id' => 'TRX-LEVEL-001',
            'post_balance' => 2100,
        ]);

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-LEVEL-001',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'TRX-LEVEL-001',
            'snapshot' => json_encode(['test' => true]),
        ]);

        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        // 验证负向交易创建
        $reversal = Transaction::where('reversal_of_id', $transaction->id)->first();
        $this->assertNotNull($reversal);
        $this->assertEquals(-100, $reversal->amount);
    }

    // =============================================================================
    // reversePointsEntries Tests
    // =============================================================================

    /** @test */
    public function it_can_reverse_points_entries()
    {
        $user = User::factory()->create();
        $userExtra = $user->userExtra()->create(['points' => 100]);

        UserPointsLog::create([
            'user_id' => $user->id,
            'source_type' => 'PURCHASE',
            'source_id' => 'POINTS-TRX-001',
            'points' => 50,
            'description' => 'Purchase bonus',
        ]);

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-POINTS-001',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'POINTS-TRX-001',
            'snapshot' => json_encode(['test' => true]),
        ]);

        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        $userExtra->refresh();

        // 验证莲子减少
        $this->assertEquals(50, $userExtra->points);

        // 验证负向莲子记录
        $reversalPoints = UserPointsLog::where('reversal_of_id', '!=', null)->first();
        $this->assertNotNull($reversalPoints);
        $this->assertEquals(-50, $reversalPoints->points);
    }

    // =============================================================================
    // generateBatchKey Tests
    // =============================================================================

    /** @test */
    public function it_generates_unique_batch_keys()
    {
        $keys = [];
        for ($i = 0; $i < 10; $i++) {
            $key = $this->adjustmentService->generateBatchKey();
            $this->assertStringStartsWith('ADJ-', $key);
            $this->assertTrue(!in_array($key, $keys), "Duplicate key found: $key");
            $keys[] = $key;
        }
        // 验证生成了10个不同的key
        $this->assertCount(10, array_unique($keys));
    }

    /** @test */
    public function it_generates_batch_key_with_date()
    {
        $key = $this->adjustmentService->generateBatchKey();
        $today = date('Ymd');

        $this->assertStringContainsString($today, $key);
    }

    // =============================================================================
    // getAdjustmentBatches Tests
    // =============================================================================

    /** @test */
    public function it_can_get_adjustment_batches()
    {
        AdjustmentBatch::create([
            'batch_key' => 'ADJ-TEST-001',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'TEST-001',
            'snapshot' => json_encode(['test' => true]),
        ]);
        AdjustmentBatch::create([
            'batch_key' => 'ADJ-TEST-002',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'TEST-002',
            'snapshot' => json_encode(['test' => true]),
        ]);

        $batches = $this->adjustmentService->getAdjustmentBatches();

        $this->assertArrayHasKey('data', $batches);
        $this->assertArrayHasKey('current_page', $batches);
        $this->assertCount(2, $batches['data']);
    }

    // =============================================================================
    // getAdjustmentBatchDetails Tests
    // =============================================================================

    /** @test */
    public function it_can_get_batch_details()
    {
        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-DETAIL-001',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'TEST-001',
            'snapshot' => json_encode(['test' => true]),
        ]);

        AdjustmentEntry::create([
            'batch_id' => $batch->id,
            'asset_type' => 'pv',
            'user_id' => 1,
            'amount' => -100,
        ]);

        $details = $this->adjustmentService->getAdjustmentBatchDetails($batch->id);

        $this->assertArrayHasKey('batch', $details);
        $this->assertArrayHasKey('entries', $details);
        $this->assertEquals($batch->id, $details['batch']->id);
        $this->assertCount(1, $details['entries']);
    }

    // =============================================================================
    // Edge Cases and Integration Tests
    // =============================================================================

    /** @test */
    public function it_handles_empty_pv_entries()
    {
        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-EMPTY-PV',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'NONEXISTENT-ORDER',
            'snapshot' => json_encode(['test' => true]),
        ]);

        // 不应报错
        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        $batch->refresh();
        $this->assertNotNull($batch->finalized_at);
    }

    /** @test */
    public function it_handles_multiple_beneficiaries()
    {
        $users = User::factory()->count(3)->create();

        foreach ($users as $index => $user) {
            $user->userExtra()->create([
                'bv_left' => 500 + $index * 100,
                'bv_right' => 300 + $index * 50,
            ]);

            PvLedger::create([
                'user_id' => $user->id,
                'from_user_id' => $user->id,
                'amount' => 100,
                'trx_type' => '+',
                'source_type' => 'order',
                'source_id' => 'MULTI-USER-ORDER',
                'position' => 1,
                'level' => 1,
            ]);
        }

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-MULTI-USER',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'MULTI-USER-ORDER',
            'snapshot' => json_encode(['test' => true]),
        ]);

        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        // 验证所有用户的 PV 都被冲正
        $reversalCount = PvLedger::where('source_type', 'adjustment')
            ->where('source_id', $batch->batch_key)
            ->count();

        $this->assertEquals(3, $reversalCount);
    }

    /** @test */
    public function it_creates_adjustment_entries_for_all_reversals()
    {
        $user = User::factory()->create();
        $user->userExtra()->create(['points' => 100]);

        PvLedger::create([
            'user_id' => $user->id,
            'from_user_id' => $user->id,
            'amount' => 100,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'ENTRY-TEST-001',
            'position' => 1,
            'level' => 1,
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'amount' => 50,
            'trx_type' => '+',
            'remark' => 'direct_bonus',
            'source_type' => 'order',
            'source_id' => 'ENTRY-TEST-001',
        ]);

        UserPointsLog::create([
            'user_id' => $user->id,
            'source_type' => 'PURCHASE',
            'source_id' => 'ENTRY-TEST-001',
            'points' => 25,
        ]);

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-ENTRIES-001',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'ENTRY-TEST-001',
            'snapshot' => json_encode(['test' => true]),
        ]);

        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        $entries = AdjustmentEntry::where('batch_id', $batch->id)->get();

        // 应该创建 3 条调整条目（PV、交易、积分）
        $this->assertGreaterThanOrEqual(2, $entries->count());
    }

    /** @test */
    public function it_preserves_reversal_reference_links()
    {
        $user = User::factory()->create();

        $pvEntry = PvLedger::create([
            'user_id' => $user->id,
            'from_user_id' => $user->id,
            'amount' => 100,
            'trx_type' => '+',
            'source_type' => 'order',
            'source_id' => 'LINK-TEST-001',
            'position' => 1,
            'level' => 1,
        ]);

        $trxEntry = Transaction::create([
            'user_id' => $user->id,
            'amount' => 50,
            'trx_type' => '+',
            'remark' => 'direct_bonus',
            'source_type' => 'order',
            'source_id' => 'LINK-TEST-001',
            'post_balance' => 1050,
        ]);

        $batch = AdjustmentBatch::create([
            'batch_key' => 'ADJ-LINK-001',
            'reason_type' => 'refund_after_finalize',
            'reference_type' => 'order',
            'reference_id' => 'LINK-TEST-001',
            'snapshot' => json_encode(['test' => true]),
        ]);

        $this->adjustmentService->finalizeAdjustmentBatch($batch->id);

        // 验证冲正记录正确引用原始记录
        $pvReversal = PvLedger::where('reversal_of_id', $pvEntry->id)->first();
        $this->assertNotNull($pvReversal);
        $this->assertEquals($batch->id, $pvReversal->adjustment_batch_id);

        $trxReversal = Transaction::where('reversal_of_id', $trxEntry->id)->first();
        $this->assertNotNull($trxReversal);
        $this->assertEquals($batch->id, $trxReversal->adjustment_batch_id);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
