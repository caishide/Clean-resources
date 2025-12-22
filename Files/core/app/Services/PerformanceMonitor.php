<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PerformanceMonitor
{
    protected static array $timers = [];
    protected static array $queryLog = [];

    /**
     * Start a performance timer
     */
    public static function startTimer(string $name): void
    {
        self::$timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
        ];
    }

    /**
     * Stop a timer and return metrics
     */
    public static function stopTimer(string $name): array
    {
        if (!isset(self::$timers[$name])) {
            return ['error' => 'Timer not found'];
        }

        $timer = self::$timers[$name];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        $metrics = [
            'name' => $name,
            'duration_ms' => round(($endTime - $timer['start']) * 1000, 2),
            'memory_used_mb' => round(($endMemory - $timer['memory_start']) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ];

        unset(self::$timers[$name]);

        // Log if slow (>500ms)
        if ($metrics['duration_ms'] > 500) {
            Log::channel('performance')->warning('Slow operation detected', $metrics);
        }

        return $metrics;
    }

    /**
     * Enable query logging
     */
    public static function enableQueryLogging(): void
    {
        DB::enableQueryLog();
    }

    /**
     * Get and analyze query log
     */
    public static function getQueryAnalysis(): array
    {
        $queries = DB::getQueryLog();
        $totalTime = 0;
        $slowQueries = [];

        foreach ($queries as $query) {
            $totalTime += $query['time'];
            if ($query['time'] > 100) { // >100ms is slow
                $slowQueries[] = [
                    'sql' => $query['query'],
                    'bindings' => $query['bindings'],
                    'time_ms' => $query['time'],
                ];
            }
        }

        return [
            'total_queries' => count($queries),
            'total_time_ms' => round($totalTime, 2),
            'slow_queries' => $slowQueries,
            'average_time_ms' => count($queries) > 0 
                ? round($totalTime / count($queries), 2) 
                : 0,
        ];
    }

    /**
     * Log request performance
     */
    public static function logRequest(string $route, float $startTime, array $extra = []): void
    {
        $duration = (microtime(true) - $startTime) * 1000;

        $data = array_merge([
            'route' => $route,
            'method' => request()->method(),
            'duration_ms' => round($duration, 2),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'ip' => request()->ip(),
            'user_id' => auth()->id(),
        ], $extra);

        if ($duration > 1000) {
            Log::channel('performance')->warning('Slow request', $data);
        } else {
            Log::channel('performance')->info('Request completed', $data);
        }
    }

    /**
     * Get system health metrics
     */
    public static function getHealthMetrics(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'memory_limit' => ini_get('memory_limit'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'db_connection' => config('database.default'),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Store metrics in cache for dashboard
     */
    public static function storeMetrics(string $key, array $metrics): void
    {
        $cacheKey = "perf_metrics:{$key}:" . date('Y-m-d-H');
        $existing = Cache::get($cacheKey, []);
        $existing[] = $metrics;

        // Keep only last 100 entries per hour
        if (count($existing) > 100) {
            $existing = array_slice($existing, -100);
        }

        Cache::put($cacheKey, $existing, 3600);
    }
}
