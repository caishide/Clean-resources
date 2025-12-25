<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PVLedgerService;
use App\Services\CarryFlash\CarryFlashContext;
use App\Services\CarryFlash\CarryFlashStrategyFactory;
use App\Services\CarryFlash\DeductPaidStrategy;
use App\Services\CarryFlash\DeductWeakStrategy;
use App\Services\CarryFlash\FlushAllStrategy;
use App\Services\CarryFlash\DisabledStrategy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;

/**
 * 结转策略单元测试
 */
class CarryFlashStrategyTest extends TestCase
{
    
    private PVLedgerService $pvService;
    private CarryFlashContext $context;
    private CarryFlashStrategyFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pvService = app(PVLedgerService::class);
        $this->context = new CarryFlashContext();
        $this->factory = new CarryFlashStrategyFactory($this->pvService);
    }

    /**
     * 测试扣除已结算 PV 策略
     */
    public function test_deduct_paid_strategy(): void
    {
        $strategy = new DeductPaidStrategy($this->pvService);
        $this->context->setStrategy($strategy);

        $weekKey = '2025-W01';
        $userSummary = [
            'user_id' => 1,
            'left_pv' => 1000,
            'right_pv' => 800,
            'weak_pv' => 800,
            'paid_pv' => 600,
        ];

        $result = $this->context->executeStrategy($weekKey, $userSummary);

        $this->assertEquals(400, $result['left_end']); // 1000 - 600
        $this->assertEquals(800, $result['right_end']); // 800 - 0
    }

    /**
     * 测试扣除弱区 PV 策略
     */
    public function test_deduct_weak_strategy(): void
    {
        $strategy = new DeductWeakStrategy($this->pvService);
        $this->context->setStrategy($strategy);

        $weekKey = '2025-W01';
        $userSummary = [
            'user_id' => 1,
            'left_pv' => 1000,
            'right_pv' => 800,
            'weak_pv' => 800,
        ];

        $result = $this->context->executeStrategy($weekKey, $userSummary);

        $this->assertEquals(200, $result['left_end']); // 1000 - 800
        $this->assertEquals(800, $result['right_end']); // 800 - 0
    }

    /**
     * 测试清空全部 PV 策略
     */
    public function test_flush_all_strategy(): void
    {
        $strategy = new FlushAllStrategy($this->pvService);
        $this->context->setStrategy($strategy);

        $weekKey = '2025-W01';
        $userSummary = [
            'user_id' => 1,
            'left_pv' => 1000,
            'right_pv' => 800,
            'weak_pv' => 800,
        ];

        $result = $this->context->executeStrategy($weekKey, $userSummary);

        $this->assertEquals(0, $result['left_end']);
        $this->assertEquals(0, $result['right_end']);
    }

    /**
     * 测试禁用结转策略
     */
    public function test_disabled_strategy(): void
    {
        $strategy = new DisabledStrategy();
        $this->context->setStrategy($strategy);

        $weekKey = '2025-W01';
        $userSummary = [
            'user_id' => 1,
            'left_pv' => 1000,
            'right_pv' => 800,
            'weak_pv' => 800,
        ];

        $result = $this->context->executeStrategy($weekKey, $userSummary);

        $this->assertEquals(1000, $result['left_end']);
        $this->assertEquals(800, $result['right_end']);
    }

    /**
     * 测试策略工厂创建策略
     */
    public function test_strategy_factory(): void
    {
        // 测试创建扣除已结算 PV 策略
        $strategy1 = $this->factory->create(CarryFlashStrategyFactory::STRATEGY_DEDUCT_PAID);
        $this->assertInstanceOf(DeductPaidStrategy::class, $strategy1);

        // 测试创建扣除弱区 PV 策略
        $strategy2 = $this->factory->create(CarryFlashStrategyFactory::STRATEGY_DEDUCT_WEAK);
        $this->assertInstanceOf(DeductWeakStrategy::class, $strategy2);

        // 测试创建清空全部 PV 策略
        $strategy3 = $this->factory->create(CarryFlashStrategyFactory::STRATEGY_FLUSH_ALL);
        $this->assertInstanceOf(FlushAllStrategy::class, $strategy3);

        // 测试创建禁用结转策略
        $strategy4 = $this->factory->create(CarryFlashStrategyFactory::STRATEGY_DISABLED);
        $this->assertInstanceOf(DisabledStrategy::class, $strategy4);
    }

    /**
     * 测试策略工厂创建无效策略
     */
    public function test_strategy_factory_invalid_strategy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("不支持的结转策略类型");

        $this->factory->create('invalid_strategy');
    }

    /**
     * 测试批量执行策略
     */
    public function test_execute_batch_strategy(): void
    {
        $strategy = new DisabledStrategy();
        $this->context->setStrategy($strategy);

        $weekKey = '2025-W01';
        $userSummaries = [
            ['user_id' => 1, 'left_pv' => 1000, 'right_pv' => 800],
            ['user_id' => 2, 'left_pv' => 500, 'right_pv' => 600],
            ['user_id' => 3, 'left_pv' => 0, 'right_pv' => 0],
        ];

        $result = $this->context->executeBatch($weekKey, $userSummaries);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertCount(3, $result['results']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * 测试获取可用策略列表
     */
    public function test_get_available_strategies(): void
    {
        $strategies = CarryFlashStrategyFactory::getAvailableStrategies();

        $this->assertIsArray($strategies);
        $this->assertArrayHasKey('deduct_paid', $strategies);
        $this->assertArrayHasKey('deduct_weak', $strategies);
        $this->assertArrayHasKey('flush_all', $strategies);
        $this->assertArrayHasKey('disabled', $strategies);
    }

    /**
     * 测试验证策略类型
     */
    public function test_is_valid_strategy(): void
    {
        $this->assertTrue(CarryFlashStrategyFactory::isValidStrategy('deduct_paid'));
        $this->assertTrue(CarryFlashStrategyFactory::isValidStrategy('deduct_weak'));
        $this->assertTrue(CarryFlashStrategyFactory::isValidStrategy('flush_all'));
        $this->assertTrue(CarryFlashStrategyFactory::isValidStrategy('disabled'));
        $this->assertFalse(CarryFlashStrategyFactory::isValidStrategy('invalid'));
    }
}