<?php

namespace Tests\Performance;

use Tests\TestCase;
use App\Models\User;
use App\Models\PvLedger;
use App\Models\WeeklySettlement;
use App\Services\SettlementServiceWithStrategy;
use App\Services\PVLedgerService;
use App\Services\PVLedgerServiceOptimized;
use App\Services\CarryFlash\CarryFlashContext;
use App\Services\CarryFlash\CarryFlashStrategyFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * 结算性能测试
 */
class SettlementPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private SettlementServiceWithStrategy $settlementService;
    private PVLedgerService $pvService;
    private PVLedgerServiceOptimized $pvServiceOptimized;
    private CarryFlashContext $carryFlashContext;
    private CarryFlashStrategyFactory $strategyFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pvService = app(PVLedgerService::class);
        $this->pvServiceOptimized = app(PVLedgerServiceOptimized::class);
        $this->carryFlashContext = app(CarryFlashContext::class);
        $this->strategyFactory = new CarryFlashStrategyFactory($this->pvService);

        $this->settlementService = new SettlementServiceWithStrategy(
            $this->pvService,
            $this->carryFlashContext,
            $this->strategyFactory
        );

        Config::set('settlement.carry_flash_strategy', 'disabled');
    }

    /**
     * 测试小规模结算性能（10 用户）
     */
    public function test_small_scale_settlement_performance(): void
    {
        // 创建 10 个用户
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

        $weekKey = '2025-P01';

        // 测量执行时间
        $startTime = microtime(true);
        $result = $this->settlementService->executeWeeklySettlement($weekKey);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000; // 转换为毫秒

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['users_processed']);

        // 性能断言：10 用户应该在 500ms 内完成
        $this->assertLessThan(500, $executionTime, "10 用户结算应该在 500ms 内完成，实际耗时: {$executionTime}ms");

        echo "\n[性能测试] 小规模结算（10 用户）耗时: {$executionTime}ms\n";
    }

    /**
     * 测试中等规模结算性能（100 用户）
     */
    public function test_medium_scale_settlement_performance(): void
    {
        // 创建 100 个用户
        $users = User::factory()->count(100)->create(['status' => 1]);

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

        $weekKey = '2025-P02';

        // 测量执行时间
        $startTime = microtime(true);
        $result = $this->settlementService->executeWeeklySettlement($weekKey);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals(100, $result['users_processed']);

        // 性能断言：100 用户应该在 2000ms 内完成
        $this->assertLessThan(2000, $executionTime, "100 用户结算应该在 2000ms 内完成，实际耗时: {$executionTime}ms");

        echo "\n[性能测试] 中等规模结算（100 用户）耗时: {$executionTime}ms\n";
    }

    /**
     * 测试大规模结算性能（500 用户）
     */
    public function test_large_scale_settlement_performance(): void
    {
        // 创建 500 个用户
        $users = User::factory()->count(500)->create(['status' => 1]);

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

        $weekKey = '2025-P03';

        // 测量执行时间
        $startTime = microtime(true);
        $result = $this->settlementService->executeWeeklySettlement($weekKey);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        // 验证结果
        $this->assertTrue($result['success']);
        $this->assertEquals(500, $result['users_processed']);

        // 性能断言：500 用户应该在 10000ms 内完成
        $this->assertLessThan(10000, $executionTime, "500 用户结算应该在 10000ms 内完成，实际耗时: {$executionTime}ms");

        echo "\n[性能测试] 大规模结算（500 用户）耗时: {$executionTime}ms\n";
    }

    /**
     * 测试 PV 查询性能对比（原始 vs 优化）
     */
    public function test_pv_query_performance_comparison(): void
    {
        // 创建用户和 PV 记录
        $user = User::factory()->create(['status' => 1]);

        // 创建 100 条 PV 记录
        for ($i = 0; $i < 100; $i++) {
            PvLedger::factory()->create([
                'user_id' => $user->id,
                'position' => rand(1, 2),
                'pv_amount' => rand(100, 500),
            ]);
        }

        // 测试原始服务性能
        $startTime = microtime(true);
        $summary1 = $this->pvService->getUserPVSummary($user->id);
        $endTime = microtime(true);
        $originalTime = ($endTime - $startTime) * 1000;

        // 测试优化服务性能
        $startTime = microtime(true);
        $summary2 = $this->pvServiceOptimized->getUserPVSummary($user->id);
        $endTime = microtime(true);
        $optimizedTime = ($endTime - $startTime) * 1000;

        // 验证结果一致
        $this->assertEquals($summary1['left_pv'], $summary2['left_pv']);
        $this->assertEquals($summary1['right_pv'], $summary2['right_pv']);

        // 性能断言：优化版本应该更快
        $improvement = (($originalTime - $optimizedTime) / $originalTime) * 100;

        echo "\n[性能对比] 原始版本耗时: {$originalTime}ms\n";
        echo "[性能对比] 优化版本耗时: {$optimizedTime}ms\n";
        echo "[性能对比] 性能提升: {$improvement}%\n";

        // 优化版本应该至少快 20%
        $this->assertGreaterThan(20, $improvement, "优化版本应该至少快 20%");
    }

    /**
     * 测试递归 PV 计算性能
     */
    public function test_recursive_pv_calculation_performance(): void
    {
        // 创建推荐链（10 层）
        $previousUser = null;
        $users = [];

        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->create([
                'status' => 1,
                'pos_id' => $previousUser ? $previousUser->id : null,
            ]);
            $users[] = $user;
            $previousUser = $user;
        }

        // 为每个用户创建 PV
        foreach ($users as $user) {
            PvLedger::factory()->create([
                'user_id' => $user->id,
                'position' => 1,
                'pv_amount' => 1000,
            ]);
        }

        // 测试递归计算性能
        $startTime = microtime(true);
        $result = $this->pvServiceOptimized->calculateRecursivePV($users[9]->id);
        $endTime = microtime(true);

        $executionTime = ($endTime - $startTime) * 1000;

        // 验证结果
        $this->assertNotEmpty($result);

        // 性能断言：10 层递归应该在 100ms 内完成
        $this->assertLessThan(100, $executionTime, "10 层递归应该在 100ms 内完成，实际耗时: {$executionTime}ms");

        echo "\n[性能测试] 递归 PV 计算（10 层）耗时: {$executionTime}ms\n";
    }

    /**
     * 测试数据库查询次数
     */
    public function test_database_query_count(): void
    {
        // 启用查询日志
        DB::enableQueryLog();

        // 创建用户和 PV
        $user = User::factory()->create(['status' => 1]);
        PvLedger::factory()->count(10)->create([
            'user_id' => $user->id,
            'position' => 1,
            'pv_amount' => 1000,
        ]);

        // 执行查询
        $summary = $this->pvServiceOptimized->getUserPVSummary($user->id);

        // 获取查询次数
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // 清除查询日志
        DB::flushQueryLog();
        DB::disableQueryLog();

        // 性能断言：查询次数应该少于 5 次
        $this->assertLessThan(5, $queryCount, "查询次数应该少于 5 次，实际: {$queryCount} 次");

        echo "\n[性能测试] 数据库查询次数: {$queryCount}\n";
    }

    /**
     * 测试内存使用
     */
    public function test_memory_usage(): void
    {
        // 获取初始内存
        $initialMemory = memory_get_usage();

        // 创建大量数据
        $users = User::factory()->count(100)->create(['status' => 1]);

        foreach ($users as $user) {
            PvLedger::factory()->create([
                'user_id' => $user->id,
                'position' => 1,
                'pv_amount' => 1000,
            ]);
        }

        // 执行结算
        $weekKey = '2025-P04';
        $this->settlementService->executeWeeklySettlement($weekKey);

        // 获取峰值内存
        $peakMemory = memory_get_peak_usage();
        $memoryUsed = ($peakMemory - $initialMemory) / 1024 / 1024; // 转换为 MB

        echo "\n[性能测试] 内存使用: {$memoryUsed}MB\n";

        // 性能断言：内存使用应该少于 50MB
        $this->assertLessThan(50, $memoryUsed, "内存使用应该少于 50MB，实际: {$memoryUsed}MB");
    }

    /**
     * 测试并发性能
     */
    public function test_concurrent_performance(): void
    {
        // 创建用户
        $users = User::factory()->count(50)->create(['status' => 1]);

        foreach ($users as $user) {
            PvLedger::factory()->create([
                'user_id' => $user->id,
                'position' => 1,
                'pv_amount' => 1000,
            ]);
        }

        // 模拟并发查询
        $startTime = microtime(true);

        // 并发执行多个查询
        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $user = $users[$i];
            $results[] = $this->pvServiceOptimized->getUserPVSummary($user->id);
        }

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        // 验证结果
        $this->assertCount(10, $results);

        echo "\n[性能测试] 并发查询（10 个）耗时: {$executionTime}ms\n";
        echo "[性能测试] 平均每个查询: " . ($executionTime / 10) . "ms\n";
    }

    /**
     * 测试缓存性能
     */
    public function test_cache_performance(): void
    {
        // 创建用户
        $user = User::factory()->create(['status' => 1]);
        PvLedger::factory()->create([
            'user_id' => $user->id,
            'position' => 1,
            'pv_amount' => 1000,
        ]);

        // 第一次查询（无缓存）
        $startTime = microtime(true);
        $summary1 = $this->pvServiceOptimized->getUserPVSummary($user->id);
        $endTime = microtime(true);
        $firstQueryTime = ($endTime - $startTime) * 1000;

        // 第二次查询（有缓存）
        $startTime = microtime(true);
        $summary2 = $this->pvServiceOptimized->getUserPVSummary($user->id);
        $endTime = microtime(true);
        $cachedQueryTime = ($endTime - $startTime) * 1000;

        // 验证结果一致
        $this->assertEquals($summary1, $summary2);

        // 缓存查询应该更快
        $improvement = (($firstQueryTime - $cachedQueryTime) / $firstQueryTime) * 100;

        echo "\n[性能测试] 首次查询耗时: {$firstQueryTime}ms\n";
        echo "[性能测试] 缓存查询耗时: {$cachedQueryTime}ms\n";
        echo "[性能测试] 性能提升: {$improvement}%\n";

        // 缓存查询应该至少快 50%
        $this->assertGreaterThan(50, $improvement, "缓存查询应该至少快 50%");
    }

    /**
     * 测试批量操作性能
     */
    public function test_batch_operation_performance(): void
    {
        // 创建用户
        $user = User::factory()->create(['status' => 1]);

        // 测试批量插入性能
        $startTime = microtime(true);

        $pvData = [];
        for ($i = 0; $i < 100; $i++) {
            $pvData[] = [
                'user_id' => $user->id,
                'position' => rand(1, 2),
                'pv_amount' => rand(100, 500),
                'trx_type' => '+',
                'source_type' => 'order',
                'source_id' => rand(1000, 9999),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('pv_ledger')->insert($pvData);

        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000;

        echo "\n[性能测试] 批量插入（100 条）耗时: {$executionTime}ms\n";

        // 批量插入应该在 100ms 内完成
        $this->assertLessThan(100, $executionTime, "批量插入应该在 100ms 内完成，实际耗时: {$executionTime}ms");
    }
}