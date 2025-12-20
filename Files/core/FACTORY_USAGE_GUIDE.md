# Binary Ecom 工厂类使用指南

## 概述
本文档介绍如何使用新创建的工厂类进行测试和开发。

## 🚀 快速开始

### 1. 运行测试
```bash
# 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 运行所有控制器测试
php artisan test

# 运行特定控制器测试
php artisan test --filter=AdminControllerTest

# 运行测试并显示覆盖率
php artisan test --coverage
```

### 2. 验证工厂类
```bash
# 运行验证脚本
php run_factory_tests.php
```

## 📚 工厂类使用示例

### AdminFactory - 管理员工厂
```php
// 创建普通管理员
$admin = Admin::factory()->create();

// 创建未验证邮箱的管理员
$admin = Admin::factory()->unverified()->create();

// 创建暂停的管理员
$admin = Admin::factory()->suspended()->create();

// 创建自定义属性的管理员
$admin = Admin::factory()->create([
    'name' => 'Custom Admin',
    'email' => 'admin@example.com',
]);
```

### DepositFactory - 存款工厂
```php
// 创建成功存款
$deposit = Deposit::factory()->successful()->create();

// 创建待处理存款
$deposit = Deposit::factory()->pending()->create();

// 创建拒绝存款
$deposit = Deposit::factory()->rejected()->create();

// 创建自定义金额的存款
$deposit = Deposit::factory()->create([
    'amount' => 500.00,
    'user_id' => $user->id,
]);
```

### WithdrawalFactory - 提款工厂
```php
// 创建成功提款
$withdrawal = Withdrawal::factory()->successful()->create();

// 创建待处理提款
$withdrawal = Withdrawal::factory()->pending()->create();

// 创建拒绝提款
$withdrawal = Withdrawal::factory()->rejected()->create();
```

### TransactionFactory - 交易工厂
```php
// 创建信用交易
$transaction = Transaction::factory()->credit()->create();

// 创建借方交易
$transaction = Transaction::factory()->debit()->create();

// 创建佣金交易
$transaction = Transaction::factory()->commission()->create();

// 指定用户ID
$transaction = Transaction::factory()->create([
    'user_id' => $user->id,
]);
```

### OrderFactory - 订单工厂
```php
// 创建待处理订单
$order = Order::factory()->pending()->create();

// 创建已发货订单
$order = Order::factory()->shipped()->create();

// 创建已取消订单
$order = Order::factory()->canceled()->create();

// 指定用户和产品
$order = Order::factory()
    ->withUser($user)
    ->withProduct($product)
    ->create();

// 创建多个订单
$orders = Order::factory()->count(5)->create();
```

### BvLogFactory - BV日志工厂
```php
// 创建左区BV记录
$bvLog = BvLog::factory()->left()->create();

// 创建右区BV记录
$bvLog = BvLog::factory()->right()->create();

// 创建增加BV记录
$bvLog = BvLog::factory()->plus()->create();

// 创建减少BV记录
$bvLog = BvLog::factory()->minus()->create();

// 指定用户
$bvLog = BvLog::factory()->withUser($user)->create();
```

### ProductFactory - 产品工厂
```php
// 创建激活产品
$product = Product::factory()->active()->create();

// 创建非激活产品
$product = Product::factory()->inactive()->create();

// 创建推荐产品
$product = Product::factory()->featured()->create();

// 指定分类
$product = Product::factory()
    ->withCategory($category)
    ->create();
```

### CategoryFactory - 分类工厂
```php
// 创建激活分类
$category = Category::factory()->active()->create();

// 创建非激活分类
$category = Category::factory()->inactive()->create();

// 创建推荐分类
$category = Category::factory()->featured()->create();
```

## 🔧 高级用法

### 批量创建数据
```php
// 创建多个管理员
$admins = Admin::factory()->count(10)->create();

// 创建不同状态的存款
$successfulDeposits = Deposit::factory()
    ->count(5)
    ->successful()
    ->create();

$pendingDeposits = Deposit::factory()
    ->count(3)
    ->pending()
    ->create();
```

### 创建关联数据
```php
// 创建用户及其交易
$user = User::factory()->create();
$transactions = Transaction::factory()
    ->count(5)
    ->create([
        'user_id' => $user->id,
    ]);

// 创建用户及其订单
$user = User::factory()->create();
$category = Category::factory()->create();
$product = Product::factory()->withCategory($category)->create();

$order = Order::factory()
    ->withUser($user)
    ->withProduct($product)
    ->create();
```

### 使用闭包自定义属性
```php
// 使用闭包动态生成属性
$admin = Admin::factory()->create([
    'email' => function () {
        return 'admin' . rand(1, 100) . '@example.com';
    },
]);

$deposit = Deposit::factory()->create([
    'final_amount' => function (array $attributes) {
        return $attributes['amount'] * 1.05; // 5% 手续费
    },
]);
```

## 📊 测试数据生成

### 创建测试用户
```php
// 创建普通用户
$user = User::factory()->create();

// 创建已付费用户
$paidUser = User::factory()->create([
    'plan_id' => 1,
    'balance' => 1000.00,
]);

// 创建活跃用户
$activeUser = User::factory()->create([
    'status' => 1,
    'ev' => 1,
    'sv' => 1,
]);
```

### 创建完整的测试场景
```php
// 创建一个完整的测试场景
function createTestScenario()
{
    // 创建用户
    $user = User::factory()->create();

    // 创建用户额外信息
    UserExtra::factory()->create([
        'user_id' => $user->id,
    ]);

    // 创建存款记录
    $deposits = Deposit::factory()
        ->count(3)
        ->create([
            'user_id' => $user->id,
        ]);

    // 创建提款记录
    $withdrawals = Withdrawal::factory()
        ->count(2)
        ->create([
            'user_id' => $user->id,
        ]);

    // 创建交易记录
    $transactions = Transaction::factory()
        ->count(10)
        ->create([
            'user_id' => $user->id,
        ]);

    // 创建订单
    $orders = Order::factory()
        ->count(5)
        ->create([
            'user_id' => $user->id,
        ]);

    // 创建BV日志
    $bvLogs = BvLog::factory()
        ->count(8)
        ->create([
            'user_id' => $user->id,
        ]);

    return [
        'user' => $user,
        'deposits' => $deposits,
        'withdrawals' => $withdrawals,
        'transactions' => $transactions,
        'orders' => $orders,
        'bvLogs' => $bvLogs,
    ];
}
```

## ⚠️ 注意事项

### 1. 数据库清理
在测试中记得使用数据库迁移：
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    // 测试代码
}
```

### 2. 工厂依赖
某些工厂类依赖于其他工厂类（如OrderFactory依赖于ProductFactory）。确保所有依赖的工厂都已正确创建。

### 3. 外键约束
创建关联数据时，确保外键关系正确。例如：
```php
$order = Order::factory()->create([
    'user_id' => $user->id,      // 正确
    'product_id' => $product->id, // 正确
]);
```

### 4. 状态值
使用Status常量而不是硬编码值：
```php
// 正确
'status' => Status::PAYMENT_SUCCESS

// 错误
'status' => 1
```

## 🎯 最佳实践

1. **使用工厂状态方法**: 优先使用`.successful()`, `.pending()`等方法，而不是手动设置状态值
2. **批量创建**: 使用`.count()`方法批量创建测试数据
3. **关联数据**: 使用`.withUser()`, `.withProduct()`等方法创建关联数据
4. **自定义属性**: 使用闭包动态生成复杂属性
5. **清理数据**: 在测试完成后清理测试数据

## 🔍 故障排除

### 工厂类未找到
```bash
# 重新生成自动加载
composer dump-autoload
```

### 数据库错误
```bash
# 重新运行迁移
php artisan migrate:fresh --seed

# 或仅刷新测试数据库
php artisan migrate:fresh --env=testing
```

### 外键约束错误
确保创建关联数据时，父记录已存在：
```php
// 错误：子记录先创建
$order = Order::factory()->create(['user_id' => $userId]);
$user = User::factory()->create(['id' => $userId]); // 太晚了

// 正确：父记录先创建
$user = User::factory()->create(['id' => $userId]);
$order = Order::factory()->create(['user_id' => $userId]);
```

## 📚 参考资源

- [Laravel 工厂文档](https://laravel.com/docs/database-testing#creating-models)
- [Faker 文档](https://fakerphp.github.io/)
- [Laravel 测试文档](https://laravel.com/docs/testing)

## 📞 支持

如有问题，请检查：
1. 所有工厂类文件是否存在
2. 模型是否使用了HasFactory特征
3. 数据库迁移是否已运行
4. 测试环境配置是否正确
