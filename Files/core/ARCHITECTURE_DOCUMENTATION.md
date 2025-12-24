# BinaryEcom 系统架构文档

## 📋 目录

- [系统概述](#系统概述)
- [技术架构](#技术架构)
- [系统分层](#系统分层)
- [核心模块](#核心模块)
- [数据库设计](#数据库设计)
- [业务流程](#业务流程)
- [设计模式](#设计模式)
- [性能优化](#性能优化)
- [安全机制](#安全机制)
- [部署架构](#部署架构)

---

## 系统概述

BinaryEcom 是一个基于 Laravel 11 的直销/MLM(多层次营销)管理系统,主要功能包括:

- **PV 账户管理**: 业绩值(Performance Value)的记录和追踪
- **奖金结算**: 周结算、季度结算,包括对碰奖、管理奖等
- **积分系统**: 莲子积分的获取和使用
- **用户管理**: 用户注册、安置关系、等级管理
- **订单管理**: 订单处理、退款调整
- **权限管理**: 基于 Spatie Laravel Permission 的 RBAC

### 系统特点

- ✅ 高性能: 通过缓存和查询优化,支持大规模用户
- ✅ 高可用: 分布式锁、事务保证数据一致性
- ✅ 可扩展: 模块化设计,易于扩展新功能
- ✅ 安全性: 完善的权限控制和数据验证

---

## 技术架构

### 技术栈

| 层级 | 技术 | 版本 | 说明 |
|------|------|------|------|
| **后端框架** | Laravel | 11.x | PHP Web 框架 |
| **编程语言** | PHP | 8.3+ | 服务端语言 |
| **数据库** | MySQL | 8.0+ | 关系型数据库 |
| **缓存** | Redis | 7.x | 缓存和队列 |
| **Web 服务器** | Nginx | 1.24+ | 反向代理 |
| **PHP-FPM** | PHP-FPM | 8.3+ | PHP 进程管理 |
| **队列** | Laravel Queue | - | 异步任务处理 |
| **权限** | Spatie Permission | 6.x | RBAC 权限管理 |

### 系统架构图

```
┌─────────────────────────────────────────────────────────────┐
│                         客户端层                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│  │ Web 前端 │  │ 移动端   │  │ 管理后台 │  │ 第三方   │   │
│  └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      API 网关层 (Nginx)                       │
│                    SSL 终止、负载均衡、限流                    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    应用层 (Laravel 11)                        │
│  ┌──────────────────────────────────────────────────────┐  │
│  │              路由层 (Routes)                          │  │
│  │  - API 路由  - Web 路由  - 管理员路由                 │  │
│  └──────────────────────────────────────────────────────┘  │
│                              │                               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │            中间件层 (Middleware)                      │  │
│  │  - 认证  - 权限  - 限流  - 日志  - CORS               │  │
│  └──────────────────────────────────────────────────────┘  │
│                              │                               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │            控制器层 (Controllers)                     │  │
│  │  - API 控制器  - 管理员控制器  - Web 控制器           │  │
│  └──────────────────────────────────────────────────────┘  │
│                              │                               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │            服务层 (Services)                          │  │
│  │  - 结算服务  - PV 服务  - 调整服务  - 积分服务        │  │
│  └──────────────────────────────────────────────────────┘  │
│                              │                               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │            仓储层 (Repositories)                      │  │
│  │  - 奖金仓储  - 用户仓储  - 订单仓储                   │  │
│  └──────────────────────────────────────────────────────┘  │
│                              │                               │
│  ┌──────────────────────────────────────────────────────┐  │
│  │            模型层 (Models)                            │  │
│  │  - User  - PvLedger  - Transaction  - Settlement     │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                        数据层                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │   MySQL      │  │    Redis     │  │   文件存储    │     │
│  │  (主数据库)   │  │  (缓存/队列)  │  │  (本地/OSS)   │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

---

## 系统分层

### 1. 路由层 (Routes)

**位置**: `routes/`

**职责**:
- 定义 API 端点
- 路由分组和中间件应用
- URL 参数验证

**主要文件**:
- `routes/api.php` - 主 API 路由
- `routes/api_settlement.php` - 结算相关路由
- `routes/web.php` - Web 路由

**示例**:
```php
Route::middleware(['auth:sanctum', 'api.admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('settlements', [SettlementController::class, 'getSettlements']);
        Route::post('settlements/execute', [SettlementController::class, 'executeSettlement']);
    });
```

---

### 2. 中间件层 (Middleware)

**位置**: `app/Http/Middleware/`

**职责**:
- 请求认证和授权
- 请求日志记录
- 限流和防护
- CORS 处理

**核心中间件**:
- `auth:sanctum` - Sanctum Token 认证
- `api.admin` - 管理员权限验证
- `throttle` - API 限流
- `CheckPermission` - 自定义权限检查

**示例**:
```php
public function handle(Request $request, Closure $next)
{
    if (!Auth::check() || !Auth::user()->hasRole('admin')) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }
    return $next($request);
}
```

---

### 3. 控制器层 (Controllers)

**位置**: `app/Http/Controllers/`

**职责**:
- 处理 HTTP 请求
- 参数验证
- 调用服务层
- 返回响应

**设计原则**:
- **瘦控制器**: 控制器只负责请求处理,不包含业务逻辑
- **单一职责**: 每个控制器只负责一个资源
- **依赖注入**: 通过构造函数注入服务

**示例**:
```php
class SettlementController extends Controller
{
    public function __construct(
        private SettlementService $settlementService,
        private PVLedgerService $pvLedgerService
    ) {}
    
    public function executeSettlement(Request $request): JsonResponse
    {
        $week = $request->input('week');
        $result = $this->settlementService->executeWeeklySettlement($week);
        
        return response()->json(['status' => 'success', 'data' => $result]);
    }
}
```

---

### 4. 服务层 (Services)

**位置**: `app/Services/`

**职责**:
- 实现核心业务逻辑
- 协调多个模型和仓储
- 事务管理
- 缓存策略

**核心服务**:

#### SettlementService (结算服务)
- 周结算执行
- 季度结算执行
- K 值计算
- 奖金分配

#### PVLedgerService (PV 账户服务)
- PV 记录创建
- PV 查询和统计
- 安置链计算
- PV 结转

#### AdjustmentService (调整服务)
- 退款处理
- PV 冲正
- 奖金回滚
- 积分冲正

#### PointsService (积分服务)
- 积分计算
- 积分分配
- 积分查询

**设计模式**:
- **策略模式**: CarryFlash 策略
- **仓储模式**: 数据访问抽象
- **依赖注入**: 松耦合设计

---

### 5. 仓储层 (Repositories)

**位置**: `app/Repositories/`

**职责**:
- 封装数据访问逻辑
- 复杂查询构建
- 数据缓存

**示例**:
```php
class BonusRepository
{
    public function getUserDirectBonusStats(int $userId, string $startDate, string $endDate): array
    {
        return Transaction::where('user_id', $userId)
            ->where('remark', 'direct_bonus')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('SUM(amount) as total, COUNT(*) as count')
            ->first()
            ->toArray();
    }
}
```

---

### 6. 模型层 (Models)

**位置**: `app/Models/`

**职责**:
- 数据表映射
- 模型关联
- 访问器和修改器
- 模型事件

**核心模型**:

#### User (用户)
- 用户基本信息
- 安置关系
- 等级信息

#### PvLedger (PV 账户)
- PV 流水记录
- 来源追踪
- 位置信息

#### Transaction (交易)
- 奖金发放记录
- 余额变动
- 交易类型

#### WeeklySettlement (周结算)
- 结算汇总
- K 值记录
- 结算状态

---

## 核心模块

### 1. PV 账户模块

**功能**:
- PV 记录创建
- PV 查询和统计
- 安置链计算
- PV 结转

**核心流程**:
```
订单创建 → 计算 PV → 分配到安置链 → 记录到 PvLedger → 更新用户余额
```

**关键方法**:
- `createPVEntry()` - 创建 PV 记录
- `getUserPVSummary()` - 获取 PV 汇总
- `getPlacementChain()` - 获取安置链
- `processCarryFlash()` - 处理 PV 结转

**优化点**:
- 安置链缓存 (24 小时)
- 批量插入优化
- 索引优化

---

### 2. 奖金结算模块

**功能**:
- 周结算执行
- 对碰奖计算
- 管理奖计算
- K 值调整

**核心流程**:
```
开始结算 → 计算所有用户 PV → 计算对碰奖 → 计算管理奖 → 
应用 K 值 → 生成奖金记录 → 更新用户余额 → PV 结转 → 完成
```

**关键方法**:
- `executeWeeklySettlement()` - 执行周结算
- `calculatePairBonus()` - 计算对碰奖
- `calculateMatchingBonus()` - 计算管理奖
- `calculateKFactor()` - 计算 K 值

**优化点**:
- 分布式锁防止并发
- 事务保证一致性
- 批量操作优化
- 查询优化 (避免 N+1)

---

### 3. 调整模块

**功能**:
- 退款处理
- PV 冲正
- 奖金回滚
- 积分冲正

**核心流程**:
```
创建退款 → 判断结算状态 → 
  Finalize 前: 立即冲正
  Finalize 后: 创建批次 → 人工审核 → 执行冲正
```

**关键方法**:
- `createRefundAdjustment()` - 创建退款调整
- `finalizeAdjustmentBatch()` - 执行调整批次
- `reversePVEntries()` - 冲正 PV
- `reverseBonusTransactions()` - 冲正奖金

**设计特点**:
- 批次管理
- 审计日志
- 数据快照

---

### 4. 权限管理模块

**功能**:
- 角色管理
- 权限管理
- 用户授权
- 权限验证

**技术实现**:
- Spatie Laravel Permission
- 基于 Gate 和 Policy
- 中间件验证

**权限示例**:
```php
// 定义权限
$permission = Permission::create(['name' => 'execute settlement']);

// 分配给角色
$role->givePermissionTo('execute settlement');

// 中间件验证
Route::middleware('permission:execute settlement')->group(...);
```

---

## 数据库设计

### 核心表结构

#### users (用户表)
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255),
    status TINYINT DEFAULT 1,
    balance DECIMAL(15, 2) DEFAULT 0,
    placement_id BIGINT,
    position TINYINT,
    rank_id INT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_placement (placement_id),
    INDEX idx_status (status)
);
```

#### pv_ledger (PV 账户表)
```sql
CREATE TABLE pv_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    from_user_id BIGINT UNSIGNED,
    position TINYINT,
    level INT,
    amount DECIMAL(15, 2),
    trx_type CHAR(1),
    source_type VARCHAR(50),
    source_id VARCHAR(255),
    adjustment_batch_id BIGINT UNSIGNED,
    reversal_of_id BIGINT UNSIGNED,
    created_at TIMESTAMP,
    INDEX idx_user_source (user_id, source_type, source_id),
    INDEX idx_source (source_type, source_id),
    INDEX idx_created (created_at)
);
```

#### transactions (交易表)
```sql
CREATE TABLE transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    trx_type CHAR(1),
    amount DECIMAL(15, 2),
    remark VARCHAR(255),
    source_type VARCHAR(50),
    source_id VARCHAR(255),
    post_balance DECIMAL(15, 2),
    adjustment_batch_id BIGINT UNSIGNED,
    reversal_of_id BIGINT UNSIGNED,
    created_at TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_source (source_type, source_id),
    INDEX idx_created (created_at)
);
```

#### weekly_settlements (周结算表)
```sql
CREATE TABLE weekly_settlements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    week_key VARCHAR(10) UNIQUE,
    status VARCHAR(20),
    total_users INT,
    total_pair_bonus DECIMAL(15, 2),
    total_matching_bonus DECIMAL(15, 2),
    k_factor DECIMAL(5, 4),
    created_at TIMESTAMP,
    finalized_at TIMESTAMP,
    INDEX idx_week (week_key),
    INDEX idx_status (status)
);
```

#### adjustment_batches (调整批次表)
```sql
CREATE TABLE adjustment_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_key VARCHAR(50) UNIQUE,
    reason_type VARCHAR(50),
    reference_type VARCHAR(50),
    reference_id VARCHAR(255),
    snapshot JSON,
    finalized_at TIMESTAMP,
    finalized_by BIGINT UNSIGNED,
    created_at TIMESTAMP,
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_status (finalized_at)
);
```

### 索引策略

**复合索引**:
- `pv_ledger`: (user_id, source_type, source_id)
- `transactions`: (user_id, source_type, source_id)
- `users`: (placement_id, position)

**单列索引**:
- 所有外键字段
- 常用查询字段 (status, created_at)
- 唯一索引 (username, email, batch_key)

---

## 业务流程

### 1. 订单处理流程

```
用户下单 → 创建订单 → 计算 PV → 
  分配 PV 到安置链 → 记录 PV 流水 → 
  发放直推奖 → 发放层碰奖 → 完成
```

### 2. 周结算流程

```
触发结算 → 获取分布式锁 → 
  计算所有用户 PV → 计算对碰奖 → 
  计算管理奖 → 计算 K 值 → 
  应用 K 值调整 → 生成奖金记录 → 
  更新用户余额 → PV 结转 → 
  释放锁 → 完成
```

### 3. 退款处理流程

```
创建退款 → 判断结算状态 → 
  ├─ Finalize 前: 立即冲正 PV、奖金、积分
  └─ Finalize 后: 创建调整批次 → 人工审核 → 执行冲正
```

### 4. PV 结转流程

```
结算完成 → 根据结转模式选择策略 → 
  ├─ 扣除已发放: 左右区都减去已发放 PV
  ├─ 扣除弱区: 只减去弱区 PV
  └─ 清空全部: 左右区清零
  → 更新用户余额 → 完成
```

---

## 设计模式

### 1. 策略模式 (Strategy Pattern)

**应用场景**: PV 结转逻辑

**实现**:
```php
interface CarryFlashStrategy
{
    public function execute(UserExtra $userExtra, float $leftPaid, float $rightPaid): array;
}

class DeductPaidStrategy implements CarryFlashStrategy
{
    public function execute(UserExtra $userExtra, float $leftPaid, float $rightPaid): array
    {
        // 扣除已发放 PV
    }
}

class CarryFlashContext
{
    public function __construct(private CarryFlashStrategy $strategy) {}
    
    public function executeCarryFlash(UserExtra $userExtra, float $leftPaid, float $rightPaid): array
    {
        return $this->strategy->execute($userExtra, $leftPaid, $rightPaid);
    }
}
```

**优势**:
- 符合开闭原则
- 降低圈复杂度
- 便于测试

---

### 2. 仓储模式 (Repository Pattern)

**应用场景**: 数据访问层

**实现**:
```php
interface BonusRepositoryInterface
{
    public function getUserDirectBonusStats(int $userId, string $startDate, string $endDate): array;
}

class BonusRepository implements BonusRepositoryInterface
{
    public function getUserDirectBonusStats(int $userId, string $startDate, string $endDate): array
    {
        return Transaction::where('user_id', $userId)
            ->where('remark', 'direct_bonus')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('SUM(amount) as total, COUNT(*) as count')
            ->first()
            ->toArray();
    }
}
```

**优势**:
- 数据访问逻辑集中
- 便于单元测试
- 降低耦合

---

### 3. 依赖注入 (Dependency Injection)

**应用场景**: 服务层

**实现**:
```php
class SettlementController extends Controller
{
    public function __construct(
        private SettlementService $settlementService,
        private PVLedgerService $pvLedgerService,
        private PointsService $pointsService
    ) {}
}
```

**优势**:
- 松耦合
- 便于测试
- 提高可维护性

---

## 性能优化

### 1. 查询优化

**N+1 问题解决**:
```php
// 优化前
$users = User::all();
foreach ($users as $user) {
    $user->pvLedger; // N+1 查询
}

// 优化后
$users = User::with('pvLedger')->get();
```

**嵌套循环优化**:
```php
// 优化前: O(n²)
foreach ($users as $user) {
    foreach ($allUsers as $potentialChild) {
        if ($potentialChild->placement_id === $user->id) {
            $children[] = $potentialChild;
        }
    }
}

// 优化后: O(n)
$childrenMap = [];
foreach ($allUsers as $user) {
    if ($user->placement_id) {
        $childrenMap[$user->placement_id][] = $user;
    }
}
```

---

### 2. 缓存策略

**安置链缓存**:
```php
private function getPlacementChain(User $user): array
{
    return Cache::remember(
        "placement_chain:{$user->id}",
        now()->addHours(24),
        function () use ($user) {
            return $this->calculatePlacementChain($user);
        }
    );
}
```

**查询结果缓存**:
```php
$downlines = Cache::remember(
    "downlines:{$userId}:{$generation}",
    now()->addHours(6),
    function () use ($userId, $generation) {
        return $this->buildDownlineTree($userId, $generation);
    }
);
```

---

### 3. 数据库优化

**批量插入**:
```php
$pvEntries = [];
foreach ($orders as $order) {
    $pvEntries[] = [
        'user_id' => $order->user_id,
        'amount' => $order->pv,
        // ...
    ];
}
PvLedger::insert($pvEntries);
```

**索引优化**:
```sql
-- 复合索引
CREATE INDEX idx_user_source ON pv_ledger(user_id, source_type, source_id);

-- 覆盖索引
CREATE INDEX idx_covering ON pv_ledger(user_id, amount, trx_type);
```

---

### 4. 队列异步处理

**异步任务**:
```php
// 发送通知
dispatch(new SendSettlementNotification($settlement));

// 生成报表
dispatch(new GenerateWeeklyReport($week));
```

---

## 安全机制

### 1. 认证和授权

**Laravel Sanctum**:
- Token 认证
- Token 能力控制
- 会话管理

**权限验证**:
```php
// 中间件
Route::middleware('permission:execute settlement')->group(...);

// Gate
Gate::define('execute-settlement', function ($user) {
    return $user->hasRole('admin');
});
```

---

### 2. 数据验证

**表单请求验证**:
```php
class SettlementRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'week' => 'required|string|regex:/^\d{4}-W\d{2}$/',
            'confirmed' => 'boolean',
        ];
    }
}
```

---

### 3. SQL 注入防护

**参数绑定**:
```php
// Laravel ORM 自动防护
$users = User::where('status', $status)->get();

// 原生查询使用参数绑定
$users = DB::select('SELECT * FROM users WHERE status = ?', [$status]);
```

---

### 4. XSS 防护

**输出转义**:
```blade
{{ $user->name }} // 自动转义
{!! $user->bio !!} // 不转义(谨慎使用)
```

---

### 5. CSRF 防护

**Token 验证**:
```php
// 表单中包含 CSRF token
@csrf

// API 路由排除 CSRF 验证
Route::middleware('auth:sanctum')->group(...);
```

---

## 部署架构

### 生产环境架构

```
┌─────────────────┐
│   负载均衡器     │
│    (Nginx)      │
└────────┬────────┘
         │
    ┌────┴────┐
    │         │
┌───▼───┐ ┌──▼────┐
│ Web 1 │ │ Web 2 │  (应用服务器)
└───┬───┘ └──┬────┘
    │         │
    └────┬────┘
         │
    ┌────┴────────┐
    │             │
┌───▼────┐  ┌────▼────┐
│ MySQL  │  │  Redis  │
│ (主从) │  │ (集群)   │
└────────┘  └─────────┘
```

### 服务器配置建议

**应用服务器**:
- CPU: 4 核心以上
- 内存: 8GB 以上
- 磁盘: SSD 100GB 以上

**数据库服务器**:
- CPU: 8 核心以上
- 内存: 32GB 以上
- 磁盘: SSD 500GB 以上
- 配置: 主从复制

**缓存服务器**:
- CPU: 2 核心以上
- 内存: 8GB 以上
- 磁盘: SSD 50GB 以上
- 配置: 哨兵或集群

---

## 监控和日志

### 1. 应用监控

**Laravel Telescope**:
- 请求监控
- 数据库查询
- 异常跟踪
- 性能分析

**Laravel Horizon**:
- 队列监控
- 任务吞吐量
- 失败任务
- Worker 状态

### 2. 日志管理

**日志级别**:
- DEBUG: 调试信息
- INFO: 一般信息
- WARNING: 警告信息
- ERROR: 错误信息
- CRITICAL: 严重错误

**日志通道**:
```php
'channels' => [
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => 'debug',
        'days' => 14,
    ],
    'settlement' => [
        'driver' => 'daily',
        'path' => storage_path('logs/settlement.log'),
        'level' => 'info',
        'days' => 30,
    ],
],
```

---

## 附录

### 环境变量配置

```env
APP_NAME=BinaryEcom
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://api.binaryecom.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=binaryecom
DB_USERNAME=root
DB_PASSWORD=secret

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# 结算配置
PV_UNIT_AMOUNT=3000
PAIR_RATE=0.10
TOTAL_CAP_RATE=0.7
CARRY_FLASH_MODE=0
```

### 常用命令

```bash
# 清除缓存
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 数据库迁移
php artisan migrate
php artisan migrate:rollback
php artisan migrate:fresh

# 队列处理
php artisan queue:work
php artisan queue:restart

# 权限同步
php artisan permission:cache-reset
php artisan db:seed --class=PermissionSeeder
```

---

**文档版本**: v1.0.0  
**最后更新**: 2025-12-24  
**维护团队**: BinaryEcom 开发团队