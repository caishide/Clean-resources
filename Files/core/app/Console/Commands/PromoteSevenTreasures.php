<?php

namespace App\Console\Commands;

use App\Services\SevenTreasuresService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PromoteSevenTreasures extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seven-treasures:promote {--force : 强制执行，不显示确认}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '执行七宝进阶自动晋升检查';

    protected SevenTreasuresService $sevenTreasuresService;

    public function __construct(SevenTreasuresService $sevenTreasuresService)
    {
        parent::__construct();
        $this->sevenTreasuresService = $sevenTreasuresService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('开始执行七宝进阶自动晋升检查...');
        
        // 记录开始日志
        Log::channel('seven_treasures')->info('自动晋升任务开始执行', [
            'command' => 'seven-treasures:promote',
            'timestamp' => now(),
            'force' => $this->option('force'),
        ]);

        try {
            // 执行批量晋升检查
            $result = $this->sevenTreasuresService->batchPromotionCheck();
            
            $this->info('自动晋升检查完成！');
            $this->info("检查用户数: {$result['checked']}");
            $this->info("成功晋升: {$result['promoted']}");
            $this->info("错误数量: {$result['errors']}");
            
            // 记录完成日志
            Log::channel('seven_treasures')->info('自动晋升任务完成', [
                'command' => 'seven-treasures:promote',
                'timestamp' => now(),
                'result' => $result,
            ]);
            
            // 如果有错误，显示警告
            if ($result['errors'] > 0) {
                $this->warn("警告: 有 {$result['errors']} 个用户在晋升过程中出现错误，请检查日志。");
                return 1;
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('自动晋升检查失败: ' . $e->getMessage());
            
            // 记录错误日志
            Log::channel('seven_treasures')->error('自动晋升任务执行失败', [
                'command' => 'seven-treasures:promote',
                'timestamp' => now(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return 1;
        }
    }
}