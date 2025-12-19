<?php

namespace App\Console\Commands;

use App\Services\SettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class QuarterlySettlementCommand extends Command
{
    protected $signature = 'settlement:quarterly
                            {--dry-run : 预演模式，不落库}
                            {--force : 强制执行，忽略分布式锁}
                            {--quarter= : 指定季度，例如 2025-Q1（默认上一季度）}';

    protected $description = '执行季度分红结算（1%消费商池 + 3%领导人池）';

    public function handle(SettlementService $service): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $quarter = $this->option('quarter') ?: $this->getPreviousQuarter();

        $this->info("=== 季度结算开始 ===");
        $this->info("季度: {$quarter}");
        $this->info("模式: " . ($dryRun ? '预演(Dry-Run)' : '正式'));

        $lockKey = "quarterly_settlement:" . $quarter;
        if (!$force && !Cache::lock($lockKey, 3600)->get()) {
            $this->error('另一个季度结算进程正在运行，请稍后重试或使用 --force');
            return Command::FAILURE;
        }

        try {
            $result = $service->executeQuarterlySettlement($quarter, $dryRun);
            Log::info('QuarterlySettlement: Completed', $result);
            $this->table(['指标', '数值'], [
                ['总PV', number_format($result['total_pv'] ?? 0)],
                ['1%消费商池', number_format($result['pool_stockist'] ?? 0, 2)],
                ['3%领导人池', number_format($result['pool_leader'] ?? 0, 2)],
            ]);
            if ($dryRun) {
                $this->warn('⚠️ 预演模式，未落库');
            }
            $this->info('=== 季度结算完成 ===');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('QuarterlySettlement: Failed', [
                'quarter' => $quarter,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->error('结算失败: ' . $e->getMessage());
            return Command::FAILURE;
        } finally {
            if (!$force) {
                Cache::lock($lockKey)->forceRelease();
            }
        }
    }

    private function getPreviousQuarter(): string
    {
        $now = now()->subQuarter();
        $quarter = $now->quarter;
        return $now->year . '-Q' . $quarter;
    }
}
