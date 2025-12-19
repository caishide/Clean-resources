<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * PerformanceMonitor - Monitors and logs request performance
 *
 * Tracks response times, memory usage, and database queries
 * to identify performance bottlenecks.
 */
class PerformanceMonitor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  \$next
     */
    public function handle(Request \$request, Closure \$next): Response
    {
        // Record start time and memory
        \$startTime = microtime(true);
        \$startMemory = memory_get_usage();

        // Enable query logging if in debug mode
        if (config('app.debug')) {
            DB::enableQueryLog();
        }

        // Process the request
        \$response = \$next(\$request);

        // Calculate metrics
        \$endTime = microtime(true);
        \$endMemory = memory_get_usage();
        \$executionTime = (\$endTime - \$startTime) * 1000; // Convert to milliseconds
        \$memoryUsage = (\$endMemory - \$startMemory) / 1024 / 1024; // Convert to MB

        // Get query count and total time
        \$queryCount = 0;
        \$queryTime = 0;
        if (config('app.debug')) {
            \$queries = DB::getQueryLog();
            \$queryCount = count(\$queries);
            \$queryTime = array_sum(array_column(\$queries, 'time'));
        }

        // Check for performance issues
        \$issues = [];
        \$threshold = config('monitoring.performance.response_time_threshold', 2000);

        if (\$executionTime > \$threshold) {
            \$issues[] = [
                'type' => 'slow_response',
                'value' => \$executionTime,
                'threshold' => \$threshold,
                'message' => "Response time (\${$executionTime}ms) exceeded threshold (\${$threshold}ms)",
            ];
        }

        \$memoryThreshold = config('monitoring.performance.memory_limit_threshold', 80);
        \$currentMemoryPercent = (memory_get_usage() / \$this->getMemoryLimit()) * 100;
        if (\$currentMemoryPercent > \$memoryThreshold) {
            \$issues[] = [
                'type' => 'high_memory_usage',
                'value' => \$currentMemoryPercent,
                'threshold' => \$memoryThreshold,
                'message' => "Memory usage (\${$currentMemoryPercent}%) exceeded threshold (\${\$memoryThreshold}%)",
            ];
        }

        if (\$queryCount > 100) {
            \$issues[] = [
                'type' => 'n_plus_one',
                'value' => \$queryCount,
                'threshold' => 100,
                'message' => "Too many database queries (\${$queryCount})",
            ];
        }

        // Log performance metrics
        \$metrics = [
            'method' => \$request->method(),
            'url' => \$request->fullUrl(),
            'execution_time_ms' => round(\$executionTime, 2),
            'memory_usage_mb' => round(\$memoryUsage, 2),
            'peak_memory_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
            'query_count' => \$queryCount,
            'query_time_ms' => round(\$queryTime, 2),
            'user_id' => auth()->id(),
            'ip' => \$request->ip(),
            'user_agent' => \$request->userAgent(),
            'status_code' => \$response->getStatusCode(),
        ];

        // Log to performance channel
        if (!empty(\$issues)) {
            Log::channel('performance')->warning('Performance issues detected', [
                'metrics' => \$metrics,
                'issues' => \$issues,
            ]);

            // Also send to Sentry if available
            if (app()->bound('sentry')) {
                app('sentry')->captureMessage('Performance issues detected', [
                    'level' => 'warning',
                    'extra' => [
                        'metrics' => \$metrics,
                        'issues' => \$issues,
                    ],
                ]);
            }
        } else {
            Log::channel('performance')->info('Request processed', \$metrics);
        }

        // Add performance headers to response (in debug mode only)
        if (config('app.debug')) {
            \$response->headers->set('X-Execution-Time', round(\$executionTime, 2) . 'ms');
            \$response->headers->set('X-Memory-Usage', round(\$memoryUsage, 2) . 'MB');
            \$response->headers->set('X-Query-Count', (string) \$queryCount);
        }

        return \$response;
    }

    /**
     * Get memory limit in bytes
     */
    private function getMemoryLimit(): int
    {
        \$limit = ini_get('memory_limit');
        \$unit = strtolower(substr(\$limit, -1));
        \$value = (int) \$limit;

        return match (\$unit) {
            'g' => \$value * 1024 * 1024 * 1024,
            'm' => \$value * 1024 * 1024,
            'k' => \$value * 1024,
            default => \$value,
        };
    }
}
