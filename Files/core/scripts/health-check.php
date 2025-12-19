<?php

#############################################################################
# Health Check Script for Laravel Application
# This script performs comprehensive health checks on the application
#############################################################################

class HealthChecker
{
    private array $checks = [];
    private array $results = [];
    private array $config;

    public function __construct()
    {
        $this->config = [
            'app_env' => $_ENV['APP_ENV'] ?? 'production',
            'db_host' => $_ENV['DB_HOST'] ?? 'localhost',
            'db_port' => $_ENV['DB_PORT'] ?? 3306,
            'db_database' => $_ENV['DB_DATABASE'] ?? '',
            'db_username' => $_ENV['DB_USERNAME'] ?? '',
            'db_password' => $_ENV['DB_PASSWORD'] ?? '',
            'redis_host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'redis_port' => $_ENV['REDIS_PORT'] ?? 6379,
            'cache_driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
            'session_driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
        ];
    }

    /**
     * Register a health check
     */
    public function registerCheck(string $name, callable $check, bool $critical = true): void
    {
        $this->checks[$name] = [
            'check' => $check,
            'critical' => $critical,
        ];
    }

    /**
     * Run all registered health checks
     */
    public function runChecks(): array
    {
        $this->results = [];
        $overallStatus = 'healthy';
        $criticalFailures = 0;

        foreach ($this->checks as $name => $config) {
            try {
                $result = $config['check']();
                $this->results[$name] = [
                    'status' => $result['status'],
                    'message' => $result['message'],
                    'data' => $result['data'] ?? [],
                    'critical' => $config['critical'],
                ];

                if ($result['status'] === 'error') {
                    if ($config['critical']) {
                        $criticalFailures++;
                        $overallStatus = 'unhealthy';
                    } else {
                        if ($overallStatus === 'healthy') {
                            $overallStatus = 'warning';
                        }
                    }
                }
            } catch (Exception $e) {
                $this->results[$name] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'critical' => $config['critical'],
                ];

                if ($config['critical']) {
                    $criticalFailures++;
                    $overallStatus = 'unhealthy';
                } else {
                    if ($overallStatus === 'healthy') {
                        $overallStatus = 'warning';
                    }
                }
            }
        }

        return [
            'status' => $overallStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'environment' => $this->config['app_env'],
            'checks' => $this->results,
            'summary' => [
                'total' => count($this->checks),
                'passed' => count(array_filter($this->results, fn($r) => $r['status'] === 'ok')),
                'warnings' => count(array_filter($this->results, fn($r) => $r['status'] === 'warning')),
                'failed' => count(array_filter($this->results, fn($r) => $r['status'] === 'error')),
                'critical_failures' => $criticalFailures,
            ],
        ];
    }

    /**
     * Output results in specified format
     */
    public function output(array $results, string $format = 'json'): void
    {
        switch ($format) {
            case 'json':
                header('Content-Type: application/json');
                echo json_encode($results, JSON_PRETTY_PRINT);
                break;

            case 'text':
                $this->outputText($results);
                break;

            case 'html':
                $this->outputHtml($results);
                break;

            default:
                echo json_encode($results, JSON_PRETTY_PRINT);
        }
    }

    /**
     * Output in plain text format
     */
    private function outputText(array $results): void
    {
        echo "========================================\n";
        echo "Health Check Report\n";
        echo "========================================\n";
        echo "Status: " . strtoupper($results['status']) . "\n";
        echo "Timestamp: {$results['timestamp']}\n";
        echo "Environment: {$results['environment']}\n\n";

        echo "Summary:\n";
        echo "  Total Checks: {$results['summary']['total']}\n";
        echo "  Passed: {$results['summary']['passed']}\n";
        echo "  Warnings: {$results['summary']['warnings']}\n";
        echo "  Failed: {$results['summary']['failed']}\n";
        echo "  Critical Failures: {$results['summary']['critical_failures']}\n\n";

        echo "Details:\n";
        echo "---------------------------------------\n";
        foreach ($results['checks'] as $name => $check) {
            $status = strtoupper($check['status']);
            $critical = $check['critical'] ? ' [CRITICAL]' : '';
            echo "[$status]$critical $name\n";
            echo "  Message: {$check['message']}\n\n";
        }
    }

    /**
     * Output in HTML format
     */
    private function outputHtml(array $results): void
    {
        $statusColor = [
            'healthy' => '#28a745',
            'warning' => '#ffc107',
            'unhealthy' => '#dc3545',
        ][$results['status']] ?? '#6c757d';

        header('Content-Type: text/html');
        echo "<!DOCTYPE html>\n";
        echo "<html><head><title>Health Check</title>";
        echo "<style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .header { background: {$statusColor}; color: white; padding: 20px; border-radius: 5px; }
            .summary { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; }
            .check { margin: 10px 0; padding: 10px; border-left: 4px solid; }
            .check.ok { border-color: #28a745; background: #d4edda; }
            .check.warning { border-color: #ffc107; background: #fff3cd; }
            .check.error { border-color: #dc3545; background: #f8d7da; }
            .check.critical { font-weight: bold; }
        </style></head><body>";

        echo "<div class='header'>";
        echo "<h1>Health Check Report</h1>";
        echo "<p><strong>Status:</strong> " . strtoupper($results['status']) . "</p>";
        echo "<p><strong>Timestamp:</strong> {$results['timestamp']}</p>";
        echo "<p><strong>Environment:</strong> {$results['environment']}</p>";
        echo "</div>";

        echo "<div class='summary'>";
        echo "<h2>Summary</h2>";
        echo "<ul>";
        echo "<li>Total Checks: {$results['summary']['total']}</li>";
        echo "<li>Passed: {$results['summary']['passed']}</li>";
        echo "<li>Warnings: {$results['summary']['warnings']}</li>";
        echo "<li>Failed: {$results['summary']['failed']}</li>";
        echo "<li>Critical Failures: {$results['summary']['critical_failures']}</li>";
        echo "</ul>";
        echo "</div>";

        echo "<h2>Details</h2>";
        foreach ($results['checks'] as $name => $check) {
            $class = $check['status'] . ($check['critical'] ? ' critical' : '');
            echo "<div class='check $class'>";
            echo "<h3>$name</h3>";
            echo "<p><strong>Status:</strong> " . strtoupper($check['status']) . ($check['critical'] ? ' (CRITICAL)' : '') . "</p>";
            echo "<p><strong>Message:</strong> {$check['message']}</p>";
            if (!empty($check['data'])) {
                echo "<pre>" . json_encode($check['data'], JSON_PRETTY_PRINT) . "</pre>";
            }
            echo "</div>";
        }

        echo "</body></html>";
    }

    /**
     * Initialize default health checks
     */
    public function initDefaultChecks(): void
    {
        // Application Health
        $this->registerCheck('Application Key', function () {
            $key = $_ENV['APP_KEY'] ?? '';
            if (empty($key)) {
                return ['status' => 'error', 'message' => 'APP_KEY not set'];
            }
            return ['status' => 'ok', 'message' => 'APP_KEY is set'];
        });

        // Database Connection
        $this->registerCheck('Database Connection', function () {
            try {
                $pdo = new PDO(
                    "mysql:host={$this->config['db_host']};port={$this->config['db_port']};dbname={$this->config['db_database']}",
                    $this->config['db_username'],
                    $this->config['db_password'],
                    [PDO::ATTR_TIMEOUT => 5]
                );

                $stmt = $pdo->query('SELECT 1');
                $result = $stmt->fetch();

                if ($result) {
                    return ['status' => 'ok', 'message' => 'Database connection successful'];
                }
                return ['status' => 'error', 'message' => 'Database query failed'];
            } catch (Exception $e) {
                return ['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()];
            }
        });

        // Redis Connection
        $this->registerCheck('Redis Connection', function () {
            if (!class_exists('Redis')) {
                return ['status' => 'warning', 'message' => 'Redis extension not installed', 'critical' => false];
            }

            try {
                $redis = new Redis();
                $redis->connect($this->config['redis_host'], $this->config['redis_port'], 5);
                $redis->ping();

                return ['status' => 'ok', 'message' => 'Redis connection successful'];
            } catch (Exception $e) {
                return ['status' => 'error', 'message' => 'Redis connection failed: ' . $e->getMessage()];
            }
        });

        // Disk Space
        $this->registerCheck('Disk Space', function () {
            $freeBytes = disk_free_space('.');
            $totalBytes = disk_total_space('.');

            if ($freeBytes === false || $totalBytes === false) {
                return ['status' => 'error', 'message' => 'Unable to check disk space'];
            }

            $freeGB = round($freeBytes / 1024 / 1024 / 1024, 2);
            $totalGB = round($totalBytes / 1024 / 1024 / 1024, 2);
            $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 2);

            if ($usedPercent > 90) {
                return [
                    'status' => 'error',
                    'message' => "Disk space critically low: {$usedPercent}% used",
                    'data' => ['free' => $freeGB . 'GB', 'total' => $totalGB . 'GB', 'used' => $usedPercent . '%']
                ];
            } elseif ($usedPercent > 80) {
                return [
                    'status' => 'warning',
                    'message' => "Disk space running low: {$usedPercent}% used",
                    'data' => ['free' => $freeGB . 'GB', 'total' => $totalGB . 'GB', 'used' => $usedPercent . '%']
                ];
            }

            return [
                'status' => 'ok',
                'message' => "Sufficient disk space: {$usedPercent}% used",
                'data' => ['free' => $freeGB . 'GB', 'total' => $totalGB . 'GB', 'used' => $usedPercent . '%']
            ];
        });

        // Memory Usage
        $this->registerCheck('Memory Usage', function () {
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            $memoryPeak = memory_get_peak_usage(true);

            $limitBytes = $this->parseMemoryLimit($memoryLimit);
            $usagePercent = round(($memoryUsage / $limitBytes) * 100, 2);
            $peakPercent = round(($memoryPeak / $limitBytes) * 100, 2);

            if ($usagePercent > 90) {
                return [
                    'status' => 'error',
                    'message' => "Memory usage critical: {$usagePercent}%",
                    'data' => [
                        'current' => $this->formatBytes($memoryUsage),
                        'peak' => $this->formatBytes($memoryPeak),
                        'limit' => $memoryLimit,
                        'usage_percent' => $usagePercent . '%',
                        'peak_percent' => $peakPercent . '%'
                    ]
                ];
            } elseif ($usagePercent > 75) {
                return [
                    'status' => 'warning',
                    'message' => "Memory usage high: {$usagePercent}%",
                    'data' => [
                        'current' => $this->formatBytes($memoryUsage),
                        'peak' => $this->formatBytes($memoryPeak),
                        'limit' => $memoryLimit,
                        'usage_percent' => $usagePercent . '%',
                        'peak_percent' => $peakPercent . '%'
                    ]
                ];
            }

            return [
                'status' => 'ok',
                'message' => "Memory usage normal: {$usagePercent}%",
                'data' => [
                    'current' => $this->formatBytes($memoryUsage),
                    'peak' => $this->formatBytes($memoryPeak),
                    'limit' => $memoryLimit,
                    'usage_percent' => $usagePercent . '%',
                    'peak_percent' => $peakPercent . '%'
                ]
            ];
        });

        // Cache Directory Writable
        $this->registerCheck('Cache Writable', function () {
            $cacheDir = __DIR__ . '/../storage/framework/cache';
            if (!is_writable($cacheDir)) {
                return ['status' => 'error', 'message' => 'Cache directory is not writable'];
            }
            return ['status' => 'ok', 'message' => 'Cache directory is writable'];
        });

        // Storage Directory Writable
        $this->registerCheck('Storage Writable', function () {
            $storageDir = __DIR__ . '/../storage';
            if (!is_writable($storageDir)) {
                return ['status' => 'error', 'message' => 'Storage directory is not writable'];
            }
            return ['status' => 'ok', 'message' => 'Storage directory is writable'];
        });

        // Environment Check
        $this->registerCheck('Environment', function () {
            $env = $this->config['app_env'];
            if ($env === 'production') {
                return ['status' => 'ok', 'message' => 'Running in production mode'];
            } else {
                return [
                    'status' => 'warning',
                    'message' => "Running in {$env} mode",
                    'critical' => false
                ];
            }
        });

        // HTTPS Check
        $this->registerCheck('HTTPS', function () {
            if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
                return [
                    'status' => 'warning',
                    'message' => 'HTTPS is not enabled',
                    'critical' => false
                ];
            }
            return ['status' => 'ok', 'message' => 'HTTPS is enabled'];
        });
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower(substr($limit, -1));
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// Main execution
try {
    $checker = new HealthChecker();
    $checker->initDefaultChecks();
    $results = $checker->runChecks();

    $format = $_GET['format'] ?? 'json';

    http_response_code($results['status'] === 'healthy' ? 200 : 503);
    $checker->output($results, $format);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Health check failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
