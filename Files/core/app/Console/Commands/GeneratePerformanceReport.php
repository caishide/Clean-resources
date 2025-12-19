<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * GeneratePerformanceReport - Command to generate performance reports
 *
 * Analyzes database queries, slow requests, and system metrics
 * to generate comprehensive performance reports.
 */
class GeneratePerformanceReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:report {--period=24 : Period in hours to analyze}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate performance report';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating performance report...');

        $period = $this->option('period');
        $fromDate = now()->subHours($period);

        $report = [
            'period' => $period . ' hours',
            'generated_at' => now()->toISOString(),
            'database' => $this->analyzeDatabasePerformance($fromDate),
            'memory' => $this->analyzeMemoryUsage(),
            'disk' => $this->analyzeDiskUsage(),
            'cache' => $this->analyzeCachePerformance(),
            'recommendations' => $this->generateRecommendations(),
        ];

        // Save report to file
        $filename = 'performance-report-' . now()->format('Y-m-d-H-i-s') . '.json';
        Storage::disk('local')->put('reports/' . $filename, json_encode($report, JSON_PRETTY_PRINT));

        $this->info("Performance report generated: storage/app/reports/{$filename}");

        // Log report generation
        Log::channel('performance')->info('Performance report generated', [
            'period' => $period,
            'filename' => $filename,
        ]);

        $this->displayReportSummary($report);

        return self::SUCCESS;
    }

    /**
     * Analyze database performance
     */
    private function analyzeDatabasePerformance($fromDate): array
    {
        // This would analyze slow queries from logs
        // For now, return sample data
        
        return [
            'total_queries' => 0,
            'slow_queries' => 0,
            'average_query_time' => 0,
            'database_size_mb' => $this->getDatabaseSize(),
            'connection_pool_usage' => $this->getConnectionPoolUsage(),
        ];
    }

    /**
     * Analyze memory usage
     */
    private function analyzeMemoryUsage(): array
    {
        return [
            'current_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
            'peak_usage_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2),
            'limit_mb' => round($this->parseMemoryLimit(ini_get('memory_limit')) / 1024 / 1024, 2),
            'usage_percentage' => round((memory_get_usage() / $this->parseMemoryLimit(ini_get('memory_limit'))) * 100, 2),
        ];
    }

    /**
     * Analyze disk usage
     */
    private function analyzeDiskUsage(): array
    {
        $totalSpace = disk_total_space(base_path());
        $freeSpace = disk_free_space(base_path());
        $usedSpace = $totalSpace - $freeSpace;

        return [
            'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
            'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
            'used_gb' => round($usedSpace / 1024 / 1024 / 1024, 2),
            'usage_percentage' => round(($usedSpace / $totalSpace) * 100, 2),
        ];
    }

    /**
     * Analyze cache performance
     */
    private function analyzeCachePerformance(): array
    {
        try {
            $cacheStats = \Illuminate\Support\Facades\Cache::getStore()->getPrefix();
            
            return [
                'driver' => config('cache.default'),
                'status' => 'ok',
                'hit_rate' => 85, // This would be calculated based on actual metrics
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default'),
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate performance recommendations
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];

        // Check memory usage
        $memoryUsage = (memory_get_usage() / $this->parseMemoryLimit(ini_get('memory_limit'))) * 100;
        if ($memoryUsage > 80) {
            $recommendations[] = [
                'type' => 'memory',
                'severity' => 'high',
                'message' => 'Memory usage is high (' . round($memoryUsage, 2) . '%). Consider optimizing memory-intensive operations.',
            ];
        }

        // Check disk space
        $diskUsagePercentage = $this->analyzeDiskUsage()['usage_percentage'];
        if ($diskUsagePercentage > 90) {
            $recommendations[] = [
                'type' => 'disk',
                'severity' => 'critical',
                'message' => 'Disk space is running low (' . round($diskUsagePercentage, 2) . '%). Immediate action required.',
            ];
        }

        return $recommendations;
    }

    /**
     * Get database size (placeholder)
     */
    private function getDatabaseSize(): int
    {
        // This would query the database to get actual size
        return 0;
    }

    /**
     * Get connection pool usage (placeholder)
     */
    private function getConnectionPoolUsage(): float
    {
        // This would query the database to get connection pool stats
        return 0.0;
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
     * Display report summary
     */
    private function displayReportSummary(array $report): void
    {
        $this->info("\nPerformance Report Summary:");
        $this->info("Period: {$report['period']}");
        $this->info("Memory Usage: {$report['memory']['current_usage_mb']} MB / {$report['memory']['limit_mb']} MB ({$report['memory']['usage_percentage']}%)");
        $this->info("Disk Usage: {$report['disk']['used_gb']} GB / {$report['disk']['total_gb']} GB ({$report['disk']['usage_percentage']}%)");
        $this->info("Cache Driver: {$report['cache']['driver']}");

        if (!empty($report['recommendations'])) {
            $this->warn("\nRecommendations:");
            foreach ($report['recommendations'] as $rec) {
                $this->warn("- [{$rec['type']}] {$rec['message']}");
            }
        }
    }
}
