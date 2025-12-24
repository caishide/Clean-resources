# 代码审查快速参考指南

## 🚀 快速开始

### 使用步骤
1. 打开 [`CODE_REVIEW_PROMPT.md`](CODE_REVIEW_PROMPT.md)
2. 按照审查维度逐项检查
3. 记录发现的问题
4. 生成审查报告

---

## 📋 审查检查清单（快速版）

### ✅ 必查项（P0 - 严重）

#### 性能相关
- [ ] **N+1 查询**: 检查循环中的数据库查询
- [ ] **缺少索引**: 检查常用查询字段是否有索引
- [ ] **大数据量**: 检查是否使用分块处理
- [ ] **缓存使用**: 检查热点数据是否缓存

#### 安全相关
- [ ] **SQL 注入**: 检查原始 SQL 是否安全
- [ ] **XSS 防护**: 检查输出是否转义
- [ ] **CSRF 保护**: 检查表单是否有 CSRF token
- [ ] **权限控制**: 检查敏感操作是否有权限检查
- [ ] **输入验证**: 检查所有输入是否验证

#### 业务逻辑
- [ ] **事务处理**: 检查关键操作是否使用事务
- [ ] **幂等性**: 检查重复操作是否安全
- [ ] **数据一致性**: 检查关联数据是否一致
- [ ] **异常处理**: 检查是否有异常处理

---

### ⚠️ 重要项（P1 - 高优先级）

#### 代码质量
- [ ] **方法长度**: 检查方法是否超过 50 行
- [ ] **圈复杂度**: 检查复杂度是否超过 10
- [ ] **重复代码**: 检查是否有重复逻辑
- [ ] **命名规范**: 检查命名是否清晰
- [ ] **注释完整**: 检查是否有 PHPDoc 注释

#### 架构设计
- [ ] **单一职责**: 检查类/方法是否只负责一件事
- [ ] **依赖注入**: 检查是否使用依赖注入
- [ ] **设计模式**: 检查是否合理使用设计模式
- [ ] **接口隔离**: 检查接口是否设计合理

#### 测试覆盖
- [ ] **单元测试**: 检查核心功能是否有测试
- [ ] **测试覆盖率**: 检查覆盖率是否达到 70%+
- [ ] **边界测试**: 检查是否有边界条件测试

---

### 📝 一般项（P2 - 中优先级）

#### 文档
- [ ] **API 文档**: 检查是否有 API 文档
- [ ] **架构文档**: 检查是否有架构说明
- [ ] **部署文档**: 检查是否有部署指南

#### 配置管理
- [ ] **环境变量**: 检查敏感配置是否使用环境变量
- [ ] **配置缓存**: 检查是否使用配置缓存
- [ ] **时区设置**: 检查时区是否正确

---

## 🔍 常见问题快速识别

### 1. N+1 查询问题

**问题代码**:
```php
// ❌ 错误：N+1 查询
$users = User::all();
foreach ($users as $user) {
    echo $user->profile->bio; // 每次循环都查询
}
```

**正确代码**:
```php
// ✅ 正确：使用预加载
$users = User::with('profile')->get();
foreach ($users as $user) {
    echo $user->profile->bio;
}
```

---

### 2. 缺少索引

**问题代码**:
```php
// ❌ 错误：缺少索引
PvLedger::where('user_id', $userId)
    ->where('source_type', 'order')
    ->where('source_id', $orderTrx)
    ->first();
```

**解决方案**:
```sql
-- 添加复合索引
ALTER TABLE pv_ledger 
ADD INDEX idx_user_source (user_id, source_type, source_id);
```

---

### 3. 方法过长

**问题代码**:
```php
// ❌ 错误：方法过长（100+ 行）
public function executeWeeklySettlement($weekKey)
{
    // 200+ 行逻辑...
}
```

**正确代码**:
```php
// ✅ 正确：拆分为多个方法
public function executeWeeklySettlement($weekKey)
{
    $this->validateWeekKey($weekKey);
    $summaries = $this->calculateUserSummaries($weekKey);
    $bonuses = $this->distributeBonuses($summaries);
    $this->processCarryFlash($weekKey, $summaries);
    return $bonuses;
}
```

---

### 4. 硬编码常量

**问题代码**:
```php
// ❌ 错误：硬编码
$totalCap = $totalPV * 0.7;
$pairUnit = 3000;
```

**正确代码**:
```php
// ✅ 正确：使用配置
$totalCap = $totalPV * config('settlement.total_cap_rate');
$pairUnit = config('settlement.pair_pv_unit');
```

---

### 5. 缺少异常处理

**问题代码**:
```php
// ❌ 错误：没有异常处理
$user = User::find($userId);
$bonus = $user->balance * 0.1;
```

**正确代码**:
```php
// ✅ 正确：有异常处理
try {
    $user = User::findOrFail($userId);
    $bonus = $user->balance * 0.1;
} catch (ModelNotFoundException $e) {
    Log::error("User not found", ['user_id' => $userId]);
    throw new ServiceException("用户不存在");
}
```

---

### 6. 缺少权限检查

**问题代码**:
```php
// ❌ 错误：没有权限检查
public function deleteUser($userId)
{
    User::destroy($userId);
}
```

**正确代码**:
```php
// ✅ 正确：有权限检查
public function deleteUser($userId)
{
    $this->authorize('delete', User::class);
    User::destroy($userId);
}
```

---

### 7. 不安全的查询

**问题代码**:
```php
// ❌ 错误：SQL 注入风险
$sql = "SELECT * FROM users WHERE id = " . $userId;
$results = DB::select($sql);
```

**正确代码**:
```php
// ✅ 正确：使用参数绑定
$results = DB::select("SELECT * FROM users WHERE id = ?", [$userId]);
// 或使用查询构建器
$results = DB::table('users')->where('id', $userId)->get();
```

---

### 8. 缺少事务

**问题代码**:
```php
// ❌ 错误：没有事务保护
$ledger = PvLedger::create([...]);
$user->update(['pv' => $user->pv + $amount]);
```

**正确代码**:
```php
// ✅ 正确：使用事务
DB::transaction(function () use ($user, $amount) {
    PvLedger::create([...]);
    $user->update(['pv' => $user->pv + $amount]);
});
```

---

## 📊 评分速查表

### 代码质量评分

| 指标 | 优秀 (5) | 良好 (4) | 中等 (3) | 较差 (2) | 很差 (1) |
|------|---------|---------|---------|---------|---------|
| 方法长度 | < 20 行 | < 30 行 | < 50 行 | < 80 行 | > 80 行 |
| 圈复杂度 | < 5 | < 8 | < 10 | < 15 | > 15 |
| 测试覆盖率 | > 90% | > 80% | > 70% | > 50% | < 50% |
| 代码重复率 | < 3% | < 5% | < 10% | < 15% | > 15% |

### 性能评分

| 指标 | 优秀 (5) | 良好 (4) | 中等 (3) | 较差 (2) | 很差 (1) |
|------|---------|---------|---------|---------|---------|
| API 响应时间 | < 100ms | < 200ms | < 500ms | < 1s | > 1s |
| 数据库查询时间 | < 10ms | < 50ms | < 100ms | < 200ms | > 200ms |
| 缓存命中率 | > 90% | > 80% | > 70% | > 50% | < 50% |
| N+1 查询 | 0 | 0 | 1-2 | 3-5 | > 5 |

---

## 🛠️ 常用命令

### 代码分析
```bash
# PHPStan 静态分析
vendor/bin/phpstan analyse app

# PHPMD 代码质量检测
vendor/bin/phpmd app text cleancode,codesize,controversial,design,naming,unusedcode

# PHP CS Fixer 代码格式化
vendor/bin/php-cs-fixer fix

# 运行测试
php artisan test

# 生成测试覆盖率
php artisan test --coverage
```

### 数据库分析
```bash
# 查看慢查询
mysql> SHOW FULL PROCESSLIST;

# 分析表
mysql> ANALYZE TABLE pv_ledger;

# 查看索引
mysql> SHOW INDEX FROM pv_ledger;

# 查看表结构
mysql> DESCRIBE pv_ledger;
```

### 性能分析
```bash
# 查看查询日志
tail -f storage/logs/laravel.log | grep "query"

# 使用 Laravel Debugbar
# 安装：composer require barryvdh/laravel-debugbar --dev

# 使用 Laravel Telescope
# 安装：composer require laravel/telescope
```

---

## 📝 审查报告模板

```markdown
# 代码审查报告

## 总体评估
- **项目**: BinaryEcom
- **审查日期**: 2025-XX-XX
- **总体评分**: ⭐⭐⭐⭐☆ (4/5)

## 分项评分
| 维度 | 评分 | 说明 |
|------|------|------|
| 架构设计 | ⭐⭐⭐⭐☆ | 4/5 |
| 代码质量 | ⭐⭐⭐⭐☆ | 4/5 |
| 性能优化 | ⭐⭐⭐☆☆ | 3/5 |
| 安全性 | ⭐⭐⭐☆☆ | 3/5 |
| 测试覆盖 | ⭐⭐☆☆☆ | 2/5 |

## 严重问题 (P0)
1. **N+1 查询问题**
   - 位置: `PVLedgerService.php:78`
   - 影响: 性能严重下降
   - 建议: 使用预加载或缓存

## 高优先级问题 (P1)
1. **方法过长**
   - 位置: `SettlementService.php:73`
   - 影响: 可维护性差
   - 建议: 拆分为多个方法

## 优点总结
- ✅ 架构清晰，分层合理
- ✅ 业务逻辑完整
- ✅ 事务处理得当

## 改进建议
### 短期（1-2周）
1. 添加数据库索引
2. 优化 N+1 查询
3. 编写核心测试

### 中期（3-4周）
1. 实施权限系统
2. 重构超长方法
3. 添加缓存策略

## 推荐工具
- PHPStan
- PHPMD
- PHPUnit
- Laravel Telescope
```

---

## 🎯 审查技巧

### 1. 自顶向下
- 先看整体架构
- 再看模块设计
- 最后看具体实现

### 2. 关注热点
- 优先审查核心业务逻辑
- 重点关注性能瓶颈
- 特别注意安全相关代码

### 3. 使用工具
- 静态分析工具发现问题
- 手动审查理解业务逻辑
- 性能测试验证优化效果

### 4. 记录详细
- 记录问题位置
- 说明问题影响
- 提供改进建议
- 给出示例代码

---

## 📚 参考资源

- [Laravel 最佳实践](https://github.com/alexeymezenin/laravel-best-practices)
- [Clean Code PHP](https://github.com/jupeter/clean-code-php)
- [PSR-12 编码标准](https://www.php-fig.org/psr/psr-12/)
- [Laravel 文档](https://laravel.com/docs/11.x)

---

**快速参考版本**: 1.0  
**创建日期**: 2025-12-24  
**配套文档**: [`CODE_REVIEW_PROMPT.md`](CODE_REVIEW_PROMPT.md)