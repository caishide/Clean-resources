# BinaryEcom 性能监控配置指南

## 📋 目录

- [概述](#概述)
- [Laravel Telescope 配置](#laravel-telescope-配置)
- [Laravel Horizon 配置](#laravel-horizon-配置)
- [监控指标](#监控指标)
- [告警配置](#告警配置)
- [最佳实践](#最佳实践)

---

## 概述

BinaryEcom 使用 Laravel Telescope 和 Laravel Horizon 进行性能监控和队列管理。

### 监控工具

| 工具 | 用途 | 官方文档 |
|------|------|---------|
| **Laravel Telescope** | 应用调试和监控 | https://laravel.com/docs/telescope |
| **Laravel Horizon** | 队列监控和管理 | https://laravel.com/docs/horizon |

### 监控目标

- ✅ 请求响应时间
- ✅ 数据库查询性能
- ✅ 队列任务执行
- ✅ 异常和错误跟踪
- ✅ 缓存命中率
- ✅ 内存使用情况

---

## Laravel Telescope 配置

### 1. 安装

```bash
# 安装 Telescope
composer require laravel/telescope --dev

# 发布配置文件
php artisan telescope:install

# 发布迁移文件
php artisan vendor:publish --tag=telescope-migrations

# 运行迁移
php artisan migrate
```

### 2. 配置文件

**位置**: `config/telescope.php`

```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telescope Domain
    |--------------------------------------------------------------------------
    */
    'domain' => env('TELESCOPE_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Telescope Path
    |--------------------------------------------------------------------------
    */
    'path' => env('TELESCOPE_PATH', 'telescope'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Storage Driver
    |--------------------------------------------------------------------------
    */
    'driver' => env('TELESCOPE_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Telescope Prune Storage
    |--------------------------------------------------------------------------
    */
    'prune' => [
        'enabled' => env('TELESCOPE_PRUNE', true),
        'hours' => env('TELESCOPE_PRUNE_HOURS', 48), // 保留 48 小时
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Watchers
    |--------------------------------------------------------------------------
    */
    'watchers' => [
        // 请求监控
        \Laravel\Telescope\Watchers\RequestWatcher::class => [
            'enabled' => env('TELESCOPE_REQUEST_WATCHER', true),
            'ignore_status_codes' => [404],
        ],

        // 命令监控
        \Laravel\Telescope\Watchers\CommandWatcher::class => [
            'enabled' => env('TELESCOPE_COMMAND_WATCHER', true),
            'ignore' => [
                'queue:work',
                'queue:listen',
                'horizon',
            ],
        ],

        // 作业监控
        \Laravel\Telescope\Watchers\JobWatcher::class => [
            'enabled' => env('TELESCOPE_JOB_WATCHER', true),
        ],

        // 数据库查询监控
        \Laravel\Telescope\Watchers\QueryWatcher::class => [
            'enabled' => env('TELESCOPE_QUERY_WATCHER', true),
            'slow' => 100, // 慢查询阈值(毫秒)
            'ignore_packages' => true,
        ],

        // 模型事件监控
        \Laravel\Telescope\Watchers\ModelWatcher::class => [
            'enabled' => env('TELESCOPE_MODEL_WATCHER', true),
            'events' => ['eloquent.*'],
            'ignore_packages' => true,
        ],

        // Redis 监控
        \Laravel\Telescope\Watchers\RedisWatcher::class => [
            'enabled' => env('TELESCOPE_REDIS_WATCHER', true),
        ],

        // 缓存监控
        \Laravel\Telescope\Watchers\CacheWatcher::class => [
            'enabled' => env('TELESCOPE_CACHE_WATCHER', true),
        ],

        // 调度任务监控
        \Laravel\Telescope\Watchers\ScheduleWatcher::class => [
            'enabled' => env('TELESCOPE_SCHEDULE_WATCHER', true),
        ],

        // 异常监控
        \Laravel\Telescope\Watchers\ExceptionWatcher::class => [
            'enabled' => env('TELESCOPE_EXCEPTION_WATCHER', true),
        ],

        // 日志监控
        \Laravel\Telescope\Watchers\LogWatcher::class => [
            'enabled' => env('TELESCOPE_LOG_WATCHER', true),
            'level' => 'error',
        ],

        // 通知监控
        \Laravel\Telescope\Watchers\NotificationWatcher::class => [
            'enabled' => env('TELESCOPE_NOTIFICATION_WATCHER', true),
        ],

        // Gate 监控
        \Laravel\Telescope\Watchers\GateWatcher::class => [
            'enabled' => env('TELESCOPE_GATE_WATCHER', true),
            'ignore_abilities' => [],
            'ignore_packages' => true,
        ],

        // HTTP 客户端监控
        \Laravel\Telescope\Watchers\ClientRequestWatcher::class => [
            'enabled' => env('TELESCOPE_CLIENT_REQUEST_WATCHER', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Ignore Paths
    |--------------------------------------------------------------------------
    */
    'ignore_paths' => [
        'telescope*',
        'horizon*',
        'api/health*',
        'api/ping',
    ],

    /*
    |--------------------------------------------------------------------------
    | Telescope Ignore Requests
    |--------------------------------------------------------------------------
    */
    'ignore_requests' => [
        'telescope*',
        'horizon*',
    ],

];
```

### 3. 环境变量配置

**位置**: `.env`

```env
# Telescope 配置
TELESCOPE_ENABLED=true
TELESCOPE_DOMAIN=telescope.binaryecom.com
TELESCOPE_PATH=telescope
TELESCOPE_DRIVER=database
TELESCOPE_PRUNE=true
TELESCOPE_PRUNE_HOURS=48

# Watcher 配置
TELESCOPE_REQUEST_WATCHER=true
TELESCOPE_COMMAND_WATCHER=true
TELESCOPE_JOB_WATCHER=true
TELESCOPE_QUERY_WATCHER=true
TELESCOPE_MODEL_WATCHER=true
TELESCOPE_REDIS_WATCHER=true
TELESCOPE_CACHE_WATCHER=true
TELESCOPE_SCHEDULE_WATCHER=true
TELESCOPE_EXCEPTION_WATCHER=true
TELESCOPE_LOG_WATCHER=true
TELESCOPE_NOTIFICATION_WATCHER=true
TELESCOPE_GATE_WATCHER=true
TELESCOPE_CLIENT_REQUEST_WATCHER=true
```

### 4. 生产环境配置

**仅允许管理员访问**:

```php
// app/Providers/TelescopeServiceProvider.php
public function register()
{
    // 仅在非生产环境或管理员用户启用
    if ($this->app->environment('local') || $this->app->runningInConsole()) {
        $this->app->register(TelescopeServiceProvider::class);
    } else {
        // 生产环境: 仅管理员可访问
        $this->app->register(TelescopeServiceProvider::class);
        
        Telescope::auth(function ($request) {
            return $request->user() && 
                   $request->user()->hasRole('admin');
        });
    }
}
```

### 5. 定时清理

**位置**: `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    // 每天凌晨 2 点清理 Telescope 数据
    $schedule->command('telescope:prune --hours=48')
             ->dailyAt('02:00')
             ->description('Prune Telescope entries older than 48 hours');
}
```

---

## Laravel Horizon 配置

### 1. 安装

```bash
# 安装 Horizon
composer require laravel/horizon

# 发布配置文件
php artisan vendor:publish --provider="Laravel\Horizon\HorizonServiceProvider"

# 运行迁移
php artisan horizon:install
```

### 2. 配置文件

**位置**: `config/horizon.php`

```php
<?php

use Illuminate\Support\Arr;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */
    'domain' => env('HORIZON_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */
    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */
    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env('HORIZON_PREFIX', 'horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        'web',
        'auth',
        // 'can:admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Wait Time Thresholds
    |--------------------------------------------------------------------------
    */
    'waits' => [
        'redis:default' => [
            'default' => 60, // 默认等待时间(秒)
            'critical' => 300, // 临界等待时间(秒)
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Job Trimming Times
    |--------------------------------------------------------------------------
    */
    'trim' => [
        'recent' => 60, // 保留最近 60 分钟的作业
        'completed' => 1440, // 保留已完成 24 小时的作业
        'recent_failed' => 10080, // 保留最近 7 天的失败作业
        'failed' => 10080, // 保留失败 7 天的作业
        'monitored' => 10080, // 保留监控 7 天的作业
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Fast Termination
    |--------------------------------------------------------------------------
    */
    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Horizon Memory Reserve (MB)
    |--------------------------------------------------------------------------
    */
    'memory_reserve' => 128,

    /*
    |--------------------------------------------------------------------------
    | Horizon Queue Worker Configuration
    |--------------------------------------------------------------------------
    */
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default', 'settlement', 'notification'],
                'balance' => 'auto', // 自动平衡
                'maxProcesses' => 10, // 最大进程数
                'maxTime' => 0, // 最大运行时间(0=无限制)
                'maxJobs' => 0, // 最大作业数(0=无限制)
                'memory' => 128, // 内存限制(MB)
                'tries' => 3, // 重试次数
                'timeout' => 60, // 超时时间(秒)
                'sleep' => 3, // 休眠时间(秒)
                'delay' => 0, // 延迟时间(秒)
            ],
        ],
        
        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'maxProcesses' => 3,
                'tries' => 3,
                'timeout' => 60,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Metrics
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'trim_snapshots' => [
            'job' => 7, // 保留 7 天的作业快照
            'queue' => 7, // 保留 7 天的队列快照
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Silenced Jobs
    |--------------------------------------------------------------------------
    */
    'silenced' => [
        // 'App\Jobs\ExampleJob',
    ],

    /*
    |--------------------------------------------------------------------------
    | Horizon Monitoring
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'allow' => [
            // 'App\Jobs\ExampleJob',
        ],
        'tags' => [
            // 'critical',
        ],
    ],

];
```

### 3. 环境变量配置

**位置**: `.env`

```env
# Horizon 配置
HORIZON_ENABLED=true
HORIZON_DOMAIN=horizon.binaryecom.com
HORIZON_PATH=horizon
HORIZON_PREFIX=horizon:

# Redis 配置
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
```

### 4. 权限配置

**位置**: `app/Providers/HorizonServiceProvider.php`

```php
<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // 仅允许管理员访问 Horizon
        Horizon::auth(function ($request) {
            return $request->user() && 
                   $request->user()->hasRole('admin');
        });
    }

    /**
     * Register the Horizon gate.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user) {
            return $user->hasRole('admin');
        });
    }
}
```

### 5. Supervisor 配置

**生产环境 Supervisor 配置**:

```ini
[program:horizon]
process_name=%(program_name)s
command=php /www/wwwroot/binaryecom20/Files/core/artisan horizon
autostart=true
autorestart=true
user=www
redirect_stderr=true
stdout_logfile=/www/wwwroot/binaryecom20/Files/core/storage/logs/horizon.log
stopwaitsecs=3600
```

**启动命令**:

```bash
# 启动 Horizon
php artisan horizon

# 停止 Horizon
php artisan horizon:terminate

# 暂停 Horizon
php artisan horizon:pause

# 恢复 Horizon
php artisan horizon:continue

# 清理失败作业
php artisan horizon:clear
```

---

## 监控指标

### 1. 应用性能指标

| 指标 | 说明 | 目标值 |
|------|------|--------|
| **响应时间** | API 平均响应时间 | < 200ms |
| **吞吐量** | 每秒处理请求数 | > 100 req/s |
| **错误率** | 错误请求占比 | < 0.1% |
| **慢查询** | 超过 100ms 的查询 | < 5% |

### 2. 数据库性能指标

| 指标 | 说明 | 目标值 |
|------|------|--------|
| **查询时间** | 平均查询时间 | < 10ms |
| **连接数** | 活动连接数 | < 80% |
| **慢查询** | 慢查询数量 | 0 |
| **死锁** | 死锁次数 | 0 |

### 3. 队列性能指标

| 指标 | 说明 | 目标值 |
|------|------|--------|
| **队列长度** | 待处理作业数 | < 1000 |
| **处理时间** | 平均作业处理时间 | < 5s |
| **失败率** | 失败作业占比 | < 1% |
| **吞吐量** | 每分钟处理作业数 | > 60 jobs/min |

### 4. 缓存性能指标

| 指标 | 说明 | 目标值 |
|------|------|--------|
| **命中率** | 缓存命中比例 | > 90% |
| **响应时间** | 缓存响应时间 | < 1ms |
| **内存使用** | Redis 内存使用 | < 80% |
| **键数量** | 存储的键数量 | < 1M |

---

## 告警配置

### 1. 邮件告警

**配置**: `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=alerts@binaryecom.com
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=alerts@binaryecom.com
MAIL_FROM_NAME="${APP_NAME} Alerts"
```

### 2. Slack 告警

**安装 Slack 通知**:

```bash
composer require laravel/slack-notification-channel
```

**配置**: `config/services.php`

```php
'slack' => [
    'notifications' => [
        'bot_user_oauth_token' => env('SLACK_BOT_TOKEN'),
        'channel' => env('SLACK_CHANNEL'),
    ],
],
```

**环境变量**: `.env`

```env
SLACK_BOT_TOKEN=xoxb-your-token
SLACK_CHANNEL=#alerts
```

### 3. 自定义告警

**创建告警服务**: `app/Services/AlertService.php`

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SystemAlert;

class AlertService
{
    /**
     * 发送告警
     */
    public function sendAlert(string $message, string $level = 'error'): void
    {
        // 记录日志
        Log::log($level, $message);
        
        // 发送邮件
        $admins = User::role('admin')->get();
        Notification::send($admins, new SystemAlert($message, $level));
        
        // 发送 Slack 通知
        // Notification::route('slack', env('SLACK_WEBHOOK_URL'))
        //     ->notify(new SystemAlert($message, $level));
    }
    
    /**
     * 检查性能指标并发送告警
     */
    public function checkPerformanceMetrics(): void
    {
        // 检查响应时间
        $avgResponseTime = $this->getAverageResponseTime();
        if ($avgResponseTime > 500) {
            $this->sendAlert("High response time detected: {$avgResponseTime}ms");
        }
        
        // 检查队列长度
        $queueSize = $this->getQueueSize();
        if ($queueSize > 5000) {
            $this->sendAlert("Large queue size detected: {$queueSize} jobs");
        }
        
        // 检查错误率
        $errorRate = $this->getErrorRate();
        if ($errorRate > 0.05) {
            $this->sendAlert("High error rate detected: " . ($errorRate * 100) . "%");
        }
    }
    
    private function getAverageResponseTime(): float
    {
        // 从 Telescope 获取平均响应时间
        return \Laravel\Telescope\Storage\EntryModel::where('type', 'request')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->avg('duration_in_milliseconds') ?? 0;
    }
    
    private function getQueueSize(): int
    {
        return \Illuminate\Support\Facades\Redis::connection()
            ->llen('queues:default:reserved');
    }
    
    private function getErrorRate(): float
    {
        $total = \Laravel\Telescope\Storage\EntryModel::where('type', 'request')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();
        
        $errors = \Laravel\Telescope\Storage\EntryModel::where('type', 'request')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->where('status', '>=', 400)
            ->count();
        
        return $total > 0 ? $errors / $total : 0;
    }
}
```

### 4. 定时检查

**位置**: `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule)
{
    // 每 5 分钟检查一次性能指标
    $schedule->call(function () {
        app(AlertService::class)->checkPerformanceMetrics();
    })->everyFiveMinutes();
}
```

---

## 最佳实践

### 1. 开发环境

- ✅ 启用所有 Telescope Watchers
- ✅ 使用简单队列配置
- ✅ 保留详细日志
- ✅ 实时监控

### 2. 生产环境

- ✅ 仅启用必要的 Watchers
- ✅ 配置自动清理
- ✅ 限制访问权限
- ✅ 使用 Supervisor 管理 Horizon
- ✅ 配置告警通知

### 3. 性能优化

- ✅ 定期清理旧数据
- ✅ 使用 Redis 缓存
- ✅ 优化慢查询
- ✅ 监控内存使用
- ✅ 调整 Worker 数量

### 4. 安全建议

- ✅ 限制访问 IP
- ✅ 使用 HTTPS
- ✅ 定期更新依赖
- ✅ 备份监控数据
- ✅ 审计日志

---

## 附录

### 常用命令

```bash
# Telescope 命令
php artisan telescope:clear          # 清除所有条目
php artisan telescope:prune          # 清理旧条目
php artisan telescope:publish        # 发布资源

# Horizon 命令
php artisan horizon                  # 启动 Horizon
php artisan horizon:pause            # 暂停所有 Worker
php artisan horizon:continue         # 恢复所有 Worker
php artisan horizon:terminate        # 优雅停止 Horizon
php artisan horizon:clear            # 清除失败作业
php artisan horizon:forget           # 忘记暂停的队列
php artisan horizon:status           # 查看 Horizon 状态

# 队列命令
php artisan queue:work               # 处理队列作业
php artisan queue:listen             # 监听队列
php artisan queue:retry              # 重试失败作业
php artisan queue:failed             # 查看失败作业
php artisan queue:flush              # 清除所有失败作业
```

### 监控仪表板

- **Telescope**: `https://telescope.binaryecom.com`
- **Horizon**: `https://horizon.binaryecom.com`

### 故障排查

**问题**: Horizon 无法启动  
**解决**: 检查 Redis 连接,确保 Redis 正在运行

**问题**: Telescope 数据过多  
**解决**: 配置自动清理,减少保留时间

**问题**: 队列堆积  
**解决**: 增加 Worker 数量,优化作业处理逻辑

---

**文档版本**: v1.0.0  
**最后更新**: 2025-12-24  
**维护团队**: BinaryEcom 开发团队