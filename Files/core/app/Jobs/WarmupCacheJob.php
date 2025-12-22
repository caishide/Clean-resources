<?php

namespace App\Jobs;

use App\Helpers\CacheHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WarmupCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;
    public $failOnTimeout = true;

    public function __construct()
    {
        $this->onQueue('cache');
    }

    public function handle(): void
    {
        try {
            Log::info("开始缓存预热");

            // 预热所有常用缓存
            CacheHelper::warmupCache();

            Log::info("缓存预热完成");

        } catch (\Exception $e) {
            Log::error("缓存预热失败", [
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical("缓存预热任务最终失败", [
            'error' => $exception->getMessage()
        ]);
    }
}
