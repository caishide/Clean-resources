# BinaryEcom 系统架构文档

## 目录

1. [系统概述](#系统概述)
2. [技术栈](#技术栈)
3. [架构设计](#架构设计)
4. [目录结构](#目录结构)
5. [核心模块](#核心模块)
6. [设计模式](#设计模式)
7. [数据库设计](#数据库设计)
8. [安全设计](#安全设计)
9. [性能优化](#性能优化)
10. [部署架构](#部署架构)

---

## 系统概述

BinaryEcom 是一个基于 Laravel 11 的直销/传销（MLM）系统，提供完整的会员管理、PV（积分值）管理、奖金计算和结算功能。

### 核心功能

- **会员管理**: 用户注册、推荐关系、左右区管理
- **PV 管理**: PV 账本、PV 计算、PV 结转
- **奖金计算**: 对碰奖、周结算、季度结算
- **权限管理**: 基于 Spatie Laravel Permission 的 RBAC 权限系统
- **调整管理**: 退款、奖金调整、惩罚等

---

## 技术栈

### 后端框架

- **Laravel 11**: PHP 8.3+ 现代化 Web 框架
- **MySQL 8.0+**: 关系型数据库
- **Redis**: 缓存和队列

### 核心依赖

```json
{
  "laravel/framework": "^11.0",
  "spatie/laravel-permission": "^6.0",
  "predis/predis": "^2.0"
}
```

### 开发工具

- **PHPUnit**: 单元测试
- **Laravel Telescope**: 调试工具
- **Laravel Horizon**: 队列监控

---

## 架构设计

### 整体架构

```
┌─────────────────────────────────────────────────────────────┐
│                         客户端层                              │
│  (Web 前端 / 移动端 / 第三方应用)                              │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                         API 网关层                            │
│  (认证 / 授权 / 限流 / 日志)                                   │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                         应用层                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  控制器层     │  │  中间件层     │  │  请求验证层   │      │
│  │ Controllers  │  │ Middleware   │  │ Form Request │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                         业务逻辑层                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  服务层       │  │  策略层       │  │  仓储层       │      │
│  │  Services    │  │  Strategies  │  │  Repositories│      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                         数据访问层                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  模型层       │  │  查询构建器   │  │  Eloquent    │      │
│  │  Models      │  │  Query Builder│  │  ORM         │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                         数据存储层                            │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │  MySQL       │  │  Redis       │  │  File System │      │
│  │  主数据库     │  │  缓存/队列    │  │  文件存储     │      │
│  └──────────────┘  └──────────────┘  └──────────────┘      │
└─────────────────────────────────────────────────────────────┘
```

### 分层架构说明

#### 1. 客户端层
- Web 前端（Vue.js/React）
- 移动端（iOS/Android）
- 第三方应用

#### 2. API 网关层
- JWT 认证
- 权限验证
- 请求限流
- 日志记录

#### 3. 应用层
- **控制器层**: 处理 HTTP 请求，调用服务层
- **中间件层**: 认证、授权、日志等横切关注点
- **请求验证层**: 输入验证和过滤

#### 4. 业务逻辑层
- **服务层**: 核心业务逻辑
- **策略层**: 可插拔的业务策略
- **仓储层**: 数据访问抽象

#### 5. 数据访问层
- **模型层**: Eloquent 模型
- **查询构建器**: 复杂查询构建
- **Eloquent ORM**: ORM 映射

#### 6. 数据存储层
- **MySQL**: 主数据库
- **Redis**: 缓存和队列
- **File System**: 文件存储

---

## 目录结构

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── UserController.php
│   │   │   ├── SettlementController.php
│   │   │   └── AdjustmentController.php
│   │   └── Api/
│   │       └── V1/
│   │           ├── UserController.php
│   │           └── SettlementController.php
│   ├── Middleware/
│   │   ├── CheckPermission.php
│   │   └── LogRequest.php
│   └── Requests/
│       ├── AdjustmentRequest.php
│       └── SettlementRequest.php
├── Models/
│   ├── User.php
│   ├── PvLedger.php
│   ├── WeeklySettlement.php
│   └── QuarterlySettlement.php
├── Services/
│   ├── PVLedgerService.php
│   ├── PVLedgerServiceOptimized.php
│   ├── SettlementService.php
│   ├── SettlementServiceRefactored.php
│   ├── SettlementServiceWithStrategy.php
│   ├── AdjustmentService.php
│   └── CarryFlash/
│       ├── CarryFlashStrategyInterface.php
│       ├── CarryFlashContext.php
│       ├── CarryFlashStrategyFactory.php
│       ├── DeductPaidStrategy.php
│       ├── DeductWeakStrategy.php
│       ├── FlushAllStrategy.php
│       └── DisabledStrategy.php
├── Exceptions/
│   ├── Handler.php
│   └── BusinessException.php
└── Traits/
    └── HasPermissionTrait.php
```

---

## 核心模块

### 1. 用户管理模块

**职责**:
- 用户注册和登录
- 用户信息管理
- 推荐关系管理
- 左右区管理

**核心类**:
- `User`: 用户模型
- `UserController`: 用户控制器
- `UserService`: 用户服务（待实现）

**关键方法**:
```php
// 获取用户的推荐链
public function getUplineChain(int $userId): array

// 获取用户的下级
public function getDownline(int $userId, int $position): ?User

// 计算用户的左右区 PV
public function calculateBinaryPV(int $userId): array
```

### 2. PV 账本管理模块

**职责**:
- PV 记录管理
- PV 计算
- PV 汇总
- PV 结转

**核心类**:
- `PvLedger`: PV 账本模型
- `PVLedgerService`: PV 账本服务
- `PVLedgerServiceOptimized`: 优化版 PV 账本服务

**关键方法**:
```php
// 记录 PV
public function creditPV(int $userId, int $position, float $amount, string $description): PvLedger

// 获取用户 PV 汇总
public function getUserPVSummary(int $userId): array

// 递归计算推荐链的 PV
public function calculateRecursivePV(int $userId): array

// 结转 PV
public function creditCarryFlash(int $userId, int $position, float $amount, string $weekKey, string $description): PvLedger
```

### 3. 结算管理模块

**职责**:
- 周结算
- 季度结算
- 奖金计算
- 结转处理

**核心类**:
- `WeeklySettlement`: 周结算模型
- `QuarterlySettlement`: 季度结算模型
- `SettlementService`: 结算服务
- `SettlementServiceRefactored`: 重构版结算服务
- `SettlementServiceWithStrategy`: 使用策略模式的结算服务

**关键方法**:
```php
// 执行周结算
public function executeWeeklySettlement(string $weekKey): array

// 执行季度结算
public function executeQuarterlySettlement(string $quarterKey): array

// 计算对碰奖
public function calculatePairingBonus(float $leftPV, float $rightPV): float

// 处理 PV 结转
public function processCarryFlash(string $weekKey, array $userSummaries): array
```

### 4. 调整管理模块

**职责**:
- 退款处理
- 奖金调整
- 惩罚处理
- 调整审批

**核心类**:
- `Adjustment`: 调整模型（待实现）
- `AdjustmentService`: 调整服务
- `AdjustmentRequest`: 调整请求验证

**关键方法**:
```php
// 处理退款
public function processRefund(int $userId, float $amount, string $reason): Adjustment

// 处理奖金调整
public function processBonusAdjustment(int $userId, float $amount, string $reason): Adjustment

// 处理惩罚
public function processPenalty(int $userId, float $amount, string $reason): Adjustment
```

### 5. 权限管理模块

**职责**:
- 角色管理
- 权限管理
- 用户授权
- 权限验证

**核心类**:
- `Role`: 角色模型（Spatie）
- `Permission`: 权限模型（Spatie）
- `CheckPermission`: 权限检查中间件
- `HasPermissionTrait`: 权限检查 Trait

**关键方法**:
```php
// 检查权限
public function checkPermission(string $permission): bool

// 检查角色
public function checkRole(string $role): bool

// 检查是否为管理员
public function isAdmin(): bool
```

---

## 设计模式

### 1. 策略模式（Strategy Pattern）

**应用场景**: PV 结转策略

**实现**:
```php
// 策略接口
interface CarryFlashStrategyInterface {
    public function process(string $weekKey, array $userSummary): array;
}

// 具体策略
class DeductPaidStrategy implements CarryFlashStrategyInterface { }
class DeductWeakStrategy implements CarryFlashStrategyInterface { }
class FlushAllStrategy implements CarryFlashStrategyInterface { }
class DisabledStrategy implements CarryFlashStrategyInterface { }

// 策略上下文
class CarryFlashContext {
    private CarryFlashStrategyInterface $strategy;
    
    public function setStrategy(CarryFlashStrategyInterface $strategy): void;
    public function executeStrategy(string $weekKey, array $userSummary): array;
}

// 策略工厂
class CarryFlashStrategyFactory {
    public function create(string $strategyType): CarryFlashStrategyInterface;
}
```

**优点**:
- 算法可以自由切换
- 避免多重条件判断
- 扩展性好

### 2. 服务层模式（Service Layer Pattern）

**应用场景**: 业务逻辑封装

**实现**:
```php
class PVLedgerService {
    public function creditPV(...): PvLedger;
    public function getUserPVSummary(...): array;
    public function calculateRecursivePV(...): array;
}

class SettlementService {
    public function executeWeeklySettlement(...): array;
    public function executeQuarterlySettlement(...): array;
}
```

**优点**:
- 业务逻辑集中管理
- 代码复用性高
- 易于测试

### 3. 仓储模式（Repository Pattern）

**应用场景**: 数据访问抽象（待实现）

**实现**:
```php
interface PvLedgerRepositoryInterface {
    public function findByUserId(int $userId): Collection;
    public function sumPVByPosition(int $userId, int $position): float;
}

class PvLedgerRepository implements PvLedgerRepositoryInterface {
    public function findByUserId(int $userId): Collection {
        return PvLedger::where('user_id', $userId)->get();
    }
}
```

**优点**:
- 数据访问逻辑集中
- 易于切换数据源
- 便于单元测试

### 4. 工厂模式（Factory Pattern）

**应用场景**: 对象创建

**实现**:
```php
class CarryFlashStrategyFactory {
    public function create(string $strategyType): CarryFlashStrategyInterface {
        return match ($strategyType) {
            'deduct_paid' => new DeductPaidStrategy(),
            'deduct_weak' => new DeductWeakStrategy(),
            'flush_all' => new FlushAllStrategy(),
            'disabled' => new DisabledStrategy(),
            default => throw new InvalidArgumentException(),
        };
    }
}
```

**优点**:
- 对象创建逻辑集中
- 易于扩展
- 解耦对象创建和使用

---

## 数据库设计

### 核心表结构

#### users（用户表）

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    pos_id BIGINT UNSIGNED COMMENT '推荐人ID',
    position TINYINT COMMENT '位置: 1=左区, 2=右区',
    status TINYINT DEFAULT 1 COMMENT '状态: 0=禁用, 1=启用',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_pos_id (pos_id),
    INDEX idx_status (status)
);
```

#### pv_ledgers（PV 账本表）

```sql
CREATE TABLE pv_ledgers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    position TINYINT NOT NULL COMMENT '位置: 1=左区, 2=右区',
    pv_amount DECIMAL(10, 2) NOT NULL,
    source_type VARCHAR(50) COMMENT '来源类型',
    source_id BIGINT UNSIGNED COMMENT '来源ID',
    description VARCHAR(255),
    details JSON COMMENT '详细信息',
    created_at TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_user_position (user_id, position),
    INDEX idx_source (source_type, source_id),
    INDEX idx_created_at (created_at)
);
```

#### weekly_settlements（周结算表）

```sql
CREATE TABLE weekly_settlements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    week_key VARCHAR(10) NOT NULL COMMENT '周期键: YYYY-Www',
    left_pv DECIMAL(10, 2) DEFAULT 0,
    right_pv DECIMAL(10, 2) DEFAULT 0,
    weak_pv DECIMAL(10, 2) DEFAULT 0,
    bonus_amount DECIMAL(10, 2) DEFAULT 0,
    settlement_date DATE,
    created_at TIMESTAMP,
    UNIQUE KEY uk_user_week (user_id, week_key),
    INDEX idx_week_key (week_key),
    INDEX idx_settlement_date (settlement_date)
);
```

#### quarterly_settlements（季度结算表）

```sql
CREATE TABLE quarterly_settlements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    quarter_key VARCHAR(10) NOT NULL COMMENT '季度键: YYYY-Qq',
    total_left_pv DECIMAL(10, 2) DEFAULT 0,
    total_right_pv DECIMAL(10, 2) DEFAULT 0,
    total_weak_pv DECIMAL(10, 2) DEFAULT 0,
    total_bonus DECIMAL(10, 2) DEFAULT 0,
    extra_bonus DECIMAL(10, 2) DEFAULT 0,
    final_bonus DECIMAL(10, 2) DEFAULT 0,
    settlement_date DATE,
    created_at TIMESTAMP,
    UNIQUE KEY uk_user_quarter (user_id, quarter_key),
    INDEX idx_quarter_key (quarter_key),
    INDEX idx_settlement_date (settlement_date)
);
```

### 索引优化

已创建的索引：

1. **users 表**
   - `idx_pos_id`: 推荐人 ID 索引
   - `idx_status`: 状态索引

2. **pv_ledgers 表**
   - `idx_user_id`: 用户 ID 索引
   - `idx_user_position`: 用户 ID + 位置复合索引
   - `idx_source`: 来源类型 + 来源 ID 复合索引
   - `idx_created_at`: 创建时间索引

3. **weekly_settlements 表**
   - `uk_user_week`: 用户 ID + 周期键唯一索引
   - `idx_week_key`: 周期键索引
   - `idx_settlement_date`: 结算日期索引

4. **quarterly_settlements 表**
   - `uk_user_quarter`: 用户 ID + 季度键唯一索引
   - `idx_quarter_key`: 季度键索引
   - `idx_settlement_date`: 结算日期索引

---

## 安全设计

### 1. 认证机制

- **JWT Token**: 基于 Laravel Sanctum 的 JWT 认证
- **Token 过期**: 默认 2 小时过期
- **刷新 Token**: 支持刷新 Token

### 2. 授权机制

- **RBAC**: 基于角色的访问控制
- **权限粒度**: 细粒度权限控制
- **权限继承**: 角色权限继承

### 3. 输入验证

- **Form Request**: 使用 Form Request 验证
- **自定义规则**: 自定义验证规则
- **错误消息**: 中文错误消息

### 4. SQL 注入防护

- **Eloquent ORM**: 使用 ORM 防止 SQL 注入
- **参数绑定**: 使用参数绑定
- **输入过滤**: 输入数据过滤

### 5. XSS 防护

- **输出转义**: 自动转义输出
- **CSRF 保护**: CSRF Token 验证
- **Content Security Policy**: CSP 头部设置

### 6. 敏感数据保护

- **密码加密**: 使用 bcrypt 加密
- **数据脱敏**: 敏感数据脱敏显示
- **日志脱敏**: 日志中敏感信息脱敏

---

## 性能优化

### 1. 数据库优化

- **索引优化**: 创建合适的索引
- **查询优化**: 优化 SQL 查询
- **N+1 问题**: 解决 N+1 查询问题
- **批量操作**: 使用批量插入和更新

### 2. 缓存策略

- **Redis 缓存**: 使用 Redis 缓存热点数据
- **缓存分层**: 多级缓存策略
- **缓存失效**: 合理的缓存失效策略
- **缓存预热**: 系统启动时预热缓存

### 3. 查询优化

- **递归 CTE**: 使用递归 CTE 优化层级查询
- **批量查询**: 减少数据库查询次数
- **延迟加载**: 使用延迟加载减少内存占用

### 4. 代码优化

- **方法拆分**: 拆分超长方法
- **设计模式**: 使用设计模式提高代码质量
- **依赖注入**: 使用依赖注入降低耦合

### 5. 队列处理

- **异步任务**: 使用队列处理耗时任务
- **任务调度**: 使用 Laravel Scheduler 定时执行任务
- **失败重试**: 任务失败自动重试

---

## 部署架构

### 生产环境推荐架构

```
┌─────────────────────────────────────────────────────────────┐
│                         负载均衡器                            │
│                    (Nginx / HAProxy)                         │
└─────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│   应用服务器 1   │ │   应用服务器 2   │ │   应用服务器 3   │
│  (Laravel App)  │ │  (Laravel App)  │ │  (Laravel App)  │
└─────────────────┘ └─────────────────┘ └─────────────────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             ▼
              ┌──────────────────────────────┐
              │         数据库集群            │
              │  (MySQL Master-Slave)         │
              └──────────────────────────────┘
                             │
              ┌──────────────────────────────┐
              │         缓存集群              │
              │  (Redis Cluster)              │
              └──────────────────────────────┘
```

### 服务器配置建议

#### 应用服务器

- **CPU**: 4 核心以上
- **内存**: 8GB 以上
- **磁盘**: SSD 100GB 以上
- **PHP**: 8.3+
- **Nginx**: 1.20+

#### 数据库服务器

- **CPU**: 8 核心以上
- **内存**: 16GB 以上
- **磁盘**: SSD 500GB 以上
- **MySQL**: 8.0+

#### 缓存服务器

- **CPU**: 4 核心以上
- **内存**: 8GB 以上
- **磁盘**: SSD 100GB 以上
- **Redis**: 6.0+

### 部署步骤

1. **环境准备**
   - 安装 PHP 8.3+
   - 安装 Composer
   - 安装 Nginx
   - 安装 MySQL 8.0+
   - 安装 Redis 6.0+

2. **代码部署**
   - 克隆代码仓库
   - 安装依赖: `composer install --optimize-autoloader --no-dev`
   - 配置环境变量: `.env`
   - 生成应用密钥: `php artisan key:generate`

3. **数据库初始化**
   - 创建数据库
   - 执行迁移: `php artisan migrate`
   - 填充数据: `php artisan db:seed`

4. **缓存配置**
   - 配置 Redis 连接
   - 清除缓存: `php artisan cache:clear`
   - 预热缓存: `php artisan cache:warmup`

5. **队列配置**
   - 配置队列驱动
   - 启动队列工作进程: `php artisan queue:work --daemon`

6. **定时任务配置**
   - 配置 Cron 任务
   - 添加 Laravel Scheduler: `* * * * * php /path-to-project/artisan schedule:run >> /dev/null 2>&1`

7. **性能优化**
   - 优化配置缓存: `php artisan config:cache`
   - 优化路由缓存: `php artisan route:cache`
   - 优化视图缓存: `php artisan view:cache`

---

## 监控和日志

### 1. 应用监控

- **Laravel Telescope**: 应用性能监控
- **Laravel Horizon**: 队列监控
- **Sentry**: 错误追踪

### 2. 日志管理

- **日志级别**: DEBUG, INFO, WARNING, ERROR, CRITICAL
- **日志存储**: 文件存储 + 数据库存储
- **日志轮转**: 按日期轮转
- **日志分析**: ELK Stack

### 3. 性能监控

- **响应时间**: API 响应时间监控
- **数据库查询**: 慢查询监控
- **缓存命中率**: 缓存命中率监控
- **队列任务**: 队列任务执行监控

---

## 总结

BinaryEcom 系统采用现代化的架构设计，具有以下特点：

1. **分层架构**: 清晰的分层架构，职责分明
2. **设计模式**: 使用多种设计模式，提高代码质量
3. **性能优化**: 多层次的性能优化策略
4. **安全设计**: 完善的安全机制
5. **可扩展性**: 良好的可扩展性，易于维护和升级

通过以上架构设计，BinaryEcom 系统能够稳定、高效地运行，满足直销/传销系统的业务需求。