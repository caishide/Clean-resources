<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Redis;

/**
 * HealthController - System Health Monitoring
 *
 * Provides endpoints for monitoring system health including:
 * - Database connectivity
 * - Cache connectivity
 * - Disk space
 * - Memory usage
 * - Application status
 */
class HealthController extends Controller
{
    /**
     * Check overall system health
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function check(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'disk_space' => $this->checkDiskSpace(),
            'memory' => $this->checkMemory(),
            'app' => $this->checkApplication(),
        ];

        $overallStatus = collect($checks)->every(fn($check) => $check['status'] === 'ok') ? 'ok' : 'error';

        $response = [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'version' => app()->version(),
            'checks' => $checks,
        ];

        // Log health check results
        Log::channel('health')->info('Health check performed', $response);

        $statusCode = $overallStatus === 'ok' ? 200 : 503;

        return response()->json($response, $statusCode);
    }

    /**
     * Check database connectivity
     *
     * @return array
     */
    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = (microtime(true) - $start) * 1000;

            return [
                'status' => 'ok',
                'message' => 'Database connection successful',
                'response_time_ms' => round($responseTime, 2),
                'connection' => config('database.default'),
            ];
        } catch (\Exception $e) {
            Log::channel('health')->error('Database health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache connectivity
     *
     * @return array
     */
    private function checkCache(): array
    {
        try {
            $cacheDriver = config('cache.default');
            $testKey = 'health_check_' . time();
            $testValue = 'test_' . now()->toISOString();

            // Try to write and read from cache
            Cache::put($testKey, $testValue, 10);
            $cachedValue = Cache::get($testKey);
            Cache::forget($testKey);

            if ($cachedValue === $testValue) {
                return [
                    'status' => 'ok',
                    'message' => 'Cache connection successful',
                    'driver' => $cacheDriver,
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Cache read/write test failed',
            ];
        } catch (\Exception $e) {
            Log::channel('health')->error('Cache health check failed', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'message' => 'Cache connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check disk space
     *
     * @return array
     */
    private function checkDiskSpace(): array
    {
        try {
            $totalSpace = disk_total_space(base_path());
            $freeSpace = disk_free_space(base_path());
            $usedSpace = $totalSpace - $freeSpace;
            $usedPercentage = ($usedSpace / $totalSpace) * 100;

            $status = $usedPercentage < 90 ? 'ok' : ($usedPercentage < 95 ? 'warning' : 'error');

            return [
                'status' => $status,
                'message' => 'Disk space check',
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'used_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
                'used_percentage' => round($usedPercentage, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Disk space check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check memory usage
     *
     * @return array
     */
    private function checkMemory(): array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $memoryUsagePercentage = ($memoryUsage / $memoryLimit) * 100;

            $status = $memoryUsagePercentage < 80 ? 'ok' : ($memoryUsagePercentage < 90 ? 'warning' : 'error');

            return [
                'status' => $status,
                'message' => 'Memory usage check',
                'current_mb' => round($memoryUsage / 1024 / 1024, 2),
                'peak_mb' => round($memoryPeak / 1024 / 1024, 2),
                'limit_mb' => round($memoryLimit / 1024 / 1024, 2),
                'usage_percentage' => round($memoryUsagePercentage, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Memory check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Check application status
     *
     * @return array
     */
    private function checkApplication(): array
    {
        try {
            // Check if migrations are up to date
            $migrationsTableExists = Schema::hasTable('migrations');
            $cacheStatus = app()->has('cache') ? 'ok' : 'warning';

            return [
                'status' => 'ok',
                'message' => 'Application is running',
                'uptime' => $this->getUptime(),
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'migrations_table' => $migrationsTableExists ? 'exists' : 'missing',
                'cache' => $cacheStatus,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Application check failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Get application uptime
     */
    private function getUptime(): string
    {
        if (file_exists(base_path('.app_start_time'))) {
            $startTime = file_get_contents(base_path('.app_start_time'));
            $uptimeSeconds = time() - (int) $startTime;

            $days = floor($uptimeSeconds / 86400);
            $hours = floor(($uptimeSeconds % 86400) / 3600);
            $minutes = floor(($uptimeSeconds % 3600) / 60);

            return sprintf('%d days, %d hours, %d minutes', $days, $hours, $minutes);
        }

        return 'unknown';
    }

    /**
     * Get detailed system metrics
     */
    public function metrics(Request $request): JsonResponse
    {
        $metrics = [
            'system' => [
                'cpu_usage' => sys_getloadavg(),
                'memory' => [
                    'used' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true),
                    'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
                ],
            ],
            'database' => [
                'connections' => DB::getNumConnections(),
                'connection_name' => DB::connection()->getName(),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'status' => $this->checkCache()['status'],
            ],
            'application' => [
                'environment' => app()->environment(),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
            ],
        ];

        return response()->json($metrics);
    }
}
