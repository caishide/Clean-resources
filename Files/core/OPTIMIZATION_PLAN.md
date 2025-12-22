# Laravel 性能优化计划

**项目：** BinaryEcom20
**创建时间：** 2025-12-21 11:35:00 UTC
**版本：** v1.0
**负责人：** 技术团队

---

## 📋 执行摘要

本优化计划基于测试报告发现的问题，制定分阶段优化方案，优先级从P0到P2，预计完成时间4周。目标是将应用性能提升50%，确保生产环境稳定运行。

### 优化目标
- ✅ 响应时间：P95 < 500ms (当前未测试)
- ✅ 数据库查询：减少N+1查询，查询数 < 10/请求
- ✅ 内存使用：< 128MB/请求
- ✅ 测试通过率：100% (当前超时)
- ✅ 生产配置：100%安全合规

---

## 🎯 优化迭代计划

## 迭代 1：P0 关键问题修复 (24-48小时)

### 1.1 路由缓存问题修复 ⚠️

**问题：** 重复路由名称导致无法缓存
```bash
错误：Unable to prepare route [admin] for serialization
位置：routes/admin.php
```

**修复步骤：**
```bash
# 1. 识别重复名称
grep -rn "->name('admin.login')" routes/admin.php

# 2. 修改路由名称
# 原：Route::post('/', 'login')->name('login');
# 改：Route::post('/', 'login')->name('admin.login');

# 3. 清除缓存
php artisan route:clear

# 4. 重新缓存
php artisan route:cache

# 5. 验证
php artisan route:list | grep admin.login
```

**回滚方案：**
```bash
# 恢复备份
git checkout HEAD -- routes/admin.php

# 清除缓存
php artisan route:clear
```

**验收标准：**
```bash
php artisan route:cache
# 成功，无错误信息
```

### 1.2 API路由修复 ⚠️

**问题：** /api/health 返回404

**修复步骤：**
```php
// 1. 检查 app/Providers/RouteServiceProvider.php
public function boot(): void
{
    $this->routes(function () {
        Route::middleware('api')
            ->prefix('api')
            ->group(base_path('routes/api.php'));

        Route::middleware('web')
            ->group(base_path('routes/web.php'));
    });
}

// 2. 验证路由
php artisan route:list | grep health
# 应显示：GET|HEAD  api/health  health  App\Http\Controllers\HealthController@check

// 3. 测试
curl http://localhost/api/health
```

**回滚方案：**
```bash
git checkout HEAD -- app/Providers/RouteServiceProvider.php
```

**验收标准：**
```bash
curl -s http://localhost/api/health | jq '.status'
# 应返回："ok"
```

### 1.3 生产配置修复 ⚠️

**问题：** .env 配置不安全

**修改文件：.env.production**
```bash
# 安全性
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# 缓存
CACHE_DRIVER=redis
CACHE_PREFIX=bc20_prod
CACHE_TTL=3600

# Session
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict

# 队列
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

# 日志
LOG_LEVEL=warning
LOG_CHANNEL=stack

# 开发工具 (禁用)
TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false
```

**验证脚本：**
```bash
#!/bin/bash
# scripts/validate-production-config.sh

echo "检查生产配置..."

# 检查 APP_DEBUG
if grep -q "APP_DEBUG=true" .env.production; then
    echo "❌ APP_DEBUG 仍为 true"
    exit 1
fi

# 检查缓存驱动
if ! grep -q "CACHE_DRIVER=redis" .env.production; then
    echo "❌ 未使用 Redis 缓存"
    exit 1
fi

echo "✅ 配置检查通过"
```

**回滚方案：**
```bash
cp .env .env.production.backup
# 恢复时：cp .env.production.backup .env.production
```

---

## 迭代 2：P1 性能优化 (3-5天)

### 2.1 Redis 缓存配置

**安装 Redis：**
```bash
# Ubuntu/Debian
apt-get update
apt-get install redis-server

# 配置
sed -i 's/supervised no/supervised systemd/' /etc/redis/redis.conf
systemctl enable redis-server
systemctl start redis-server

# 测试
redis-cli ping
# 应返回：PONG
```

**Laravel 配置：config/cache.php**
```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', 'bc20_cache'),
    ],
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'username' => env('REDIS_USERNAME'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
],
```

**缓存键设计：**
```php
// config/cache-keys.php
return [
    'user_profile' => 'user:profile:{user_id}',
    'user_balance' => 'user:balance:{user_id}',
    'general_settings' => 'app:general_settings',
    'languages' => 'app:languages',
    'gateways' => 'app:gateways',
];
```

**实现缓存助手：**
```php
// app/Helpers/CacheHelper.php
class CacheHelper
{
    public static function rememberUserProfile(int $userId, callable $callback, int $ttl = 3600): array
    {
        $key = "user:profile:{$userId}";

        return Cache::remember($key, $ttl, $callback);
    }

    public static function forgetUserProfile(int $userId): void
    {
        Cache::forget("user:profile:{$userId}");
    }
}
```

**性能测试脚本：**
```php
// tests/Performance/CachePerformanceTest.php
public function test_user_profile_cache_performance()
{
    $userId = 1;

    // 第一次查询 (缓存未命中)
    $start = microtime(true);
    $profile1 = CacheHelper::rememberUserProfile($userId, function() use ($userId) {
        return User::with('userExtras')->find($userId)->toArray();
    });
    $firstQueryTime = (microtime(true) - $start) * 1000;

    // 第二次查询 (缓存命中)
    $start = microtime(true);
    $profile2 = CacheHelper::rememberUserProfile($userId, function() use ($userId) {
        return User::with('userExtras')->find($userId)->toArray();
    });
    $cachedQueryTime = (microtime(true) - $start) * 1000;

    // 缓存查询应 < 5ms
    $this->assertLessThan(5, $cachedQueryTime, '缓存查询太慢');
    // 原始查询可能较慢，但应在合理范围
    $this->assertLessThan(500, $firstQueryTime, '原始查询太慢');
}
```

### 2.2 数据库查询优化

**识别 N+1 查询：**

```bash
# 启用查询日志
DB::enableQueryLog();

// 在控制器中
$users = User::all();  // 1次查询

foreach ($users as $user) {
    echo $user->userExtras->phone;  // 每次都会查询 (N+1)
}

// 打印查询数
$queries = DB::getQueryLog();
echo count($queries);  // 显示查询次数
```

**修复 N+1 查询：**

```php
// app/Http/Controllers/Admin/ManageUsersController.php

// ❌ 错误：N+1查询
public function allUsers()
{
    $users = User::all();  // 1次查询
    return view('admin.users.list', compact('users'));
}

// ✅ 正确：使用 eager loading
public function allUsers()
{
    $users = User::with(['userExtras', 'transactions' => function($query) {
        $query->latest()->limit(10);
    }])->paginate(20);

    return view('admin.users.list', compact('users'));
}
```

**数据库索引优化：**

```sql
-- 用户表额外索引
ALTER TABLE users ADD INDEX idx_status_created (status, created_at);
ALTER TABLE users ADD INDEX idx_referral_created (ref_by, created_at);

-- 交易表优化
ALTER TABLE transactions ADD INDEX idx_user_type_created (user_id, trx_type, created_at);
ALTER TABLE transactions ADD INDEX idx_remark_created (remark, created_at);

-- 订单表优化
ALTER TABLE orders ADD INDEX idx_status_created (status, created_at);
ALTER TABLE orders ADD INDEX idx_user_status (user_id, status);

-- 验证索引
SHOW INDEX FROM users;
SHOW INDEX FROM transactions;
```

**查询性能监控：**

```php
// app/Http/Middleware/QueryMonitor.php
class QueryMonitor
{
    public function handle($request, Closure $next)
    {
        DB::enableQueryLog();

        $response = $next($request);

        if (app()->environment('local', 'staging')) {
            $queries = DB::getQueryLog();

            if (count($queries) > 10) {
                Log::warning('查询数量过多', [
                    'count' => count($queries),
                    'url' => $request->fullUrl(),
                ]);
            }

            // 慢查询记录
            foreach ($queries as $query) {
                if ($query['time'] > 100) {
                    Log::warning('慢查询检测', [
                        'query' => $query['query'],
                        'time' => $query['time'],
                        'url' => $request->fullUrl(),
                    ]);
                }
            }
        }

        return $response;
    }
}
```

### 2.3 队列异步处理

**配置队列：config/queue.php**
```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

**创建队列任务：**
```php
// app/Jobs/SendWelcomeEmailJob.php
class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(): void
    {
        Mail::to($this->user->email)->send(new WelcomeEmail($this->user));
    }

    public function failed(Exception $exception): void
    {
        Log::error('欢迎邮件发送失败', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

**使用队列：**
```php
// 在控制器中
public function store(Request $request)
{
    $user = User::create($request->all());

    // 异步发送邮件
    SendWelcomeEmailJob::dispatch($user);

    return redirect()->route('users.index');
}
```

**队列监控：**
```bash
# 启动队列worker
php artisan queue:work redis --sleep=3 --tries=3 --timeout=90

# 监控队列
php artisan queue:monitor redis:default --max=100

# 查看失败任务
php artisan queue:failed

# 重试失败任务
php artisan queue:retry all
```

---

## 迭代 3：P2 架构优化 (1-2周)

### 3.1 Service 层重构

**创建 UserService：**
```php
// app/Services/UserService.php
class UserService
{
    protected $userRepository;
    protected $transactionService;

    public function __construct(
        UserRepository $userRepository,
        TransactionService $transactionService
    ) {
        $this->userRepository = $userRepository;
        $this->transactionService = $transactionService;
    }

    public function getUsersWithStats(array $filters = []): LengthAwarePaginator
    {
        return $this->userRepository->getUsersWithStats($filters);
    }

    public function updateUserBalance(int $userId, float $amount, string $type): void
    {
        DB::transaction(function() use ($userId, $amount, $type) {
            $user = $this->userRepository->findById($userId);
            $user->balance += $amount;
            $user->save();

            $this->transactionService->create([
                'user_id' => $userId,
                'amount' => $amount,
                'type' => $type,
            ]);
        });
    }
}
```

**重构控制器：**
```php
// app/Http/Controllers/Admin/ManageUsersController.php
class ManageUsersController extends Controller
{
    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function allUsers(Request $request): View
    {
        $users = $this->userService->getUsersWithStats($request->all());
        return view('admin.users.list', compact('users'));
    }

    public function updateBalance(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric',
            'type' => 'required|in:add,subtract',
        ]);

        $amount = $validated['type'] === 'add'
            ? $validated['amount']
            : -$validated['amount'];

        $this->userService->updateUserBalance($id, $amount, 'adjustment');

        return back()->with('success', '余额更新成功');
    }
}
```

### 3.2 API 文档生成

**安装 Laravel API 文档包：**
```bash
composer require darkaonline/l5-swagger
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

**配置：config/l5-swagger.php**
```php
'api' => [
    'title' => 'BinaryEcom20 API',
    'description' => 'BinaryEcom20 REST API 文档',
    'version' => '1.0.0',
],

'routes' => [
    'api' => 'api/documentation',
    'oauth2_callback' => 'api/oauth2-callback',
],

'security' => [
    'Bearer' => [
        'type' => 'apiKey',
        'name' => 'Authorization',
        'in' => 'header',
    ],
],
```

**API 文档注释：**
```php
/**
 * @OA\Get(
 *     path="/api/health",
 *     summary="健康检查",
 *     tags={"健康检查"},
 *     @OA\Response(
 *         response=200,
 *         description="成功",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="ok"),
 *             @OA\Property(property="timestamp", type="string"),
 *         )
 *     )
 * )
 */
public function check(Request $request): JsonResponse
{
    // ...
}
```

### 3.3 监控与告警

**集成 Sentry：**
```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=YOUR_SENTRY_DSN
```

**配置：config/sentry.php**
```php
'dsn' => env('SENTRY_LARAVEL_DSN'),

'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

'before_send' => function (SentryEvent $event): SentryEvent {
    // 过滤敏感信息
    if ($event->getException()) {
        $event->getException()->setContext('user', null);
    }
    return $event;
},
```

**自定义监控指标：**
```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    // 监控慢请求
    $this->app->router->pushMiddlewareToGroup('web', \App\Http\Middleware\SlowRequestMonitor::class);
}
```

---

## 📊 性能基线与目标

### 当前基线
```
响应时间：未测试 (目标：P95 < 500ms)
数据库查询：未测试 (目标：< 10/请求)
内存使用：未测试 (目标：< 128MB)
测试通过率：0% (目标：100%)
```

### 优化后目标
```
响应时间：P95 < 500ms, P99 < 1000ms
数据库查询：< 10/请求 (平均 3-5个)
内存使用：< 128MB/请求
测试通过率：100%
缓存命中率：> 80%
队列处理：< 5秒延迟
```

---

## 🔧 工具与命令

### 性能测试工具

**1. Apache Bench (ab)**
```bash
# 测试首页
ab -n 1000 -c 10 http://localhost/

# 测试 API
ab -n 1000 -c 10 -p data.json -T application/json http://localhost/api/health
```

**2. k6 压测脚本**
```javascript
// scripts/loadtest.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
    stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '2m', target: 200 },
        { duration: '5m', target: 200 },
        { duration: '2m', target: 0 },
    ],
};

export default function() {
    let response = http.get('http://localhost/api/health');

    check(response, {
        'status is 200': (r) => r.status === 200,
        'response time < 500ms': (r) => r.timings.duration < 500,
    });

    sleep(1);
}
```

**运行：**
```bash
k6 run scripts/loadtest.js
```

### 监控命令

```bash
# 实时监控
top -p $(pgrep -f "php artisan")

# MySQL 慢查询
tail -f /www/server/data/mysql-slow.log

# Redis 监控
redis-cli monitor

# Laravel 日志
tail -f storage/logs/laravel.log

# 队列监控
php artisan queue:monitor
```

---

## 📝 验收标准

### P0 验收标准
```bash
# 1. 路由缓存成功
php artisan route:cache
# ✅ 成功输出：Route cache cleared!
# ✅ 成功输出：Routes cached successfully!

# 2. API 健康检查正常
curl http://localhost/api/health
# ✅ 返回：{"status":"ok",...}

# 3. 生产配置验证
./scripts/validate-production-config.sh
# ✅ 输出：✅ 配置检查通过
```

### P1 验收标准
```bash
# 1. 缓存命中率测试
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
# ✅ 返回：'value'

# 2. 查询性能测试
php artisan test tests/Performance/DatabaseOptimizationTest.php
# ✅ 所有测试通过

# 3. 队列测试
php artisan queue:work --once
# ✅ 任务执行成功
```

### P2 验收标准
```bash
# 1. Service 层测试
php artisan test tests/Unit/Services/UserServiceTest.php
# ✅ 所有测试通过

# 2. API 文档生成
php artisan l5-swagger:generate
# ✅ 文档生成成功

# 3. 压测通过
k6 run scripts/loadtest.js
# ✅ P95 < 500ms
```

---

## 🚨 回滚方案

### 快速回滚命令

```bash
# 回滚代码
git reset --hard HEAD~1
git push --force

# 清除缓存
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 重启服务
systemctl restart php8.3-fpm
systemctl restart nginx
systemctl restart redis-server

# 数据库回滚
php artisan migrate:rollback --step=1
```

### 配置文件回滚

```bash
# 回滚 .env
cp .env.backup .env

# 回滚配置
git checkout HEAD -- config/
```

---

## 📅 时间表

| 迭代 | 任务 | 负责人 | 预计时间 | 状态 |
|------|------|--------|----------|------|
| P0 | 路由修复 | 后端团队 | 4小时 | ⏳ |
| P0 | API修复 | 后端团队 | 2小时 | ⏳ |
| P0 | 配置修复 | DevOps | 2小时 | ⏳ |
| P1 | Redis配置 | DevOps | 4小时 | ⏳ |
| P1 | 查询优化 | 后端团队 | 8小时 | ⏳ |
| P1 | 队列实现 | 后端团队 | 6小时 | ⏳ |
| P2 | Service层 | 架构师 | 12小时 | ⏳ |
| P2 | API文档 | 后端团队 | 6小时 | ⏳ |
| P2 | 监控告警 | DevOps | 8小时 | ⏳ |

**总计：52小时 (约2周)**

---

## 📞 联系与支持

**技术支持：** tech@binaryecom20.com
**紧急联系：** on-call@binaryecom20.com
**文档：** https://docs.binaryecom20.com

**每周进度会议：**
- 时间：每周一 10:00
- 地点：Zoom
- 参与：技术团队、架构师、DevOps

---

**文档版本：** v1.0
**最后更新：** 2025-12-21
**下次审查：** 2025-12-28
