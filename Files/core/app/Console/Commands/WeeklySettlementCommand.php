<?php

namespace App\Console\Commands;

use App\Services\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * 周结算Cron命令
 * 每周一凌晨2点执行
 *
 * Crontab配置: 0 2 * * 1 php artisan settlement:weekly
 */
class WeeklySettlementCommand extends Command
{
    protected $signature = 'settlement:weekly
                            {--dry-run : 预演模式，不实际发放奖金}
                            {--force : 强制执行，忽略分布式锁}
                            {--week= : 指定结算周次，默认上周}';

    protected $description = '执行周结算，计算并发放对碰奖和管理奖';

    protected SettlementService $settlementService;

    // 分布式锁配置
    const LOCK_KEY = 'weekly_settlement_lock';
    const LOCK_TIMEOUT = 3600; // 1小时

    public function __construct(SettlementService $settlementService)
    {
        parent::__construct();
        $this->settlementService = $settlementService;
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $week = $this->option('week') ?? $this->getPreviousWeek();

        $this->info("=== 周结算开始 ===");
        $this->info("周次: {$week}");
        $this->info("模式: " . ($dryRun ? '预演(Dry-Run)' : '正式'));

        Log::info('WeeklySettlement: Starting', [
            'week' => $week,
            'dry_run' => $dryRun,
            'force' => $force
        ]);

        // 获取分布式锁
        if (!$force && !$this->acquireLock($week)) {
            $this->error('另一个结算进程正在运行，请稍后重试或使用 --force 选项');
            return Command::FAILURE;
        }

        try {
            // 执行结算
            $result = $this->settlementService->executeWeeklySettlement($week, $dryRun);

            // 输出结果
            $this->outputResult($result);

            // 记录日志
            Log::info('WeeklySettlement: Completed', $result);

            $this->info("\n=== 周结算完成 ===");

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("结算失败: {$e->getMessage()}");
            Log::error('WeeklySettlement: Failed', [
                'week' => $week,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return Command::FAILURE;

        } finally {
            // 释放锁
            if (!$force) {
                $this->releaseLock($week);
            }
        }
    }

    /**
     * 获取分布式锁
     */
    protected function acquireLock(string $week): bool
    {
        $lockKey = self::LOCK_KEY . ":{$week}";
        return Cache::lock($lockKey, self::LOCK_TIMEOUT)->get();
    }

    /**
     * 释放分布式锁
     */
    protected function releaseLock(string $week): void
    {
        $lockKey = self::LOCK_KEY . ":{$week}";
        Cache::lock($lockKey)->forceRelease();
    }

    /**
     * 获取上一周的周次标识
     */
    protected function getPreviousWeek(): string
    {
        return now()->subWeek()->format('o-\WW');
    }

    /**
     * 输出结算结果
     */
    protected function outputResult(array $result): void
    {
        $this->newLine();
        $this->info("【结算统计】");
        $this->table(
            ['指标', '数值'],
            [
                ['总PV', number_format($result['total_pv'] ?? 0)],
                ['参与用户数', number_format($result['user_count'] ?? 0)],
                ['K值', number_format($result['k_factor'] ?? 0, 6)],
                ['功德池预留', number_format($result['global_reserve'] ?? 0, 2)],
                ['刚性支出', number_format($result['fixed_sales'] ?? 0, 2)],
                ['弹性奖金总额', number_format($result['variable_bonus_total'] ?? 0, 2)],
            ]
        );

        if (!empty($result['bonus_breakdown'])) {
            $this->newLine();
            $this->info("【奖金明细】");
            $this->table(
                ['奖金类型', '金额', '人数'],
                array_map(function ($type, $data) {
                    return [
                        $type,
                        number_format($data['amount'] ?? 0, 2),
                        number_format($data['count'] ?? 0)
                    ];
                }, array_keys($result['bonus_breakdown']), $result['bonus_breakdown'])
            );
        }

        if ($result['dry_run'] ?? false) {
            $this->warn("\n⚠️ 这是预演结果，未实际发放奖金");
        }
    }
}
