<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryLogServiceProvider extends ServiceProvider
{
    /**
     * 注册数据库查询监听器
     */
    public function boot(): void
    {
        // 仅在开发环境启用，排除测试环境
        if (!config('app.debug') || app()->environment('testing')) {
            return;
        }

        DB::listen(function ($query) {
            $sql = $query->sql;
            $bindings = $query->bindings;
            $time = $query->time;

            // 记录慢查询 (>100ms)
            if ($time > 100) {
                Log::channel('slow_queries')->warning('Slow query detected', [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'time_ms' => $time,
                    'connection' => $query->connectionName,
                    'trace_id' => request()?->attributes?->get('trace_id'),
                ]);
            }

            // 检测潜在N+1问题 (同一SQL执行多次)
            static $queryCount = [];
            $hash = md5($sql);
            $queryCount[$hash] = ($queryCount[$hash] ?? 0) + 1;

            if ($queryCount[$hash] > 10) {
                Log::channel('slow_queries')->error('Potential N+1 query detected', [
                    'sql' => $sql,
                    'execution_count' => $queryCount[$hash],
                    'trace_id' => request()?->attributes?->get('trace_id'),
                ]);
            }
        });
    }
}
