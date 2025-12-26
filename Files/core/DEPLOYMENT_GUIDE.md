# BinaryEcom20 数据库部署指南

> 本文档为 **新环境（测试/生产）** 数据库初始化标准流程。
>
> **核心原则**：以 `database/migrations/` 为准，不依赖 `database.sql`（仅为历史参考）。
>
> **⚠️ 重要更新 (2025-12-26)**: 已根据安全评估报告优化，增加了强制备份检查、大表操作评估、分阶段执行等安全措施。

---

## 快速开始

> **执行位置**: 所有脚本和命令均在项目根目录 `/www/wwwroot/binaryecom20/Files/core` 下执行
>
> **执行方式**: 宝塔面板终端 或 SSH 终端

```bash
# 1. 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 2. 执行部署前检查
./pre-deploy-check.sh

# 3. 执行数据库备份
./backup-database.sh

# 4. 评估表大小（可选）
./assess-tables.sh

# 5. 执行迁移
php artisan migrate --force --env=production

# 6. 执行健康检查
./health-check.sh
```

**一键部署（推荐）**:
```bash
cd /www/wwwroot/binaryecom20/Files/core
./deploy-all.sh
```

---

## 目录

- [一、部署前准备](#一部署前准备)
- [二、MySQL 准备工作](#二mysql-准备工作)
- [三、Laravel 配置](#三laravel-配置)
- [四、执行迁移](#四执行迁移)
- [五、数据填充（可选）](#五数据填充可选)
- [六、危险迁移识别](#六危险迁移识别)
- [七、回滚策略](#七回滚策略)
- [八、健康检查](#八健康检查)
- [九、上线前检查清单](#九上线前检查清单)
- [十、部署脚本](#十部署脚本)
- [十一、常见问题](#十一常见问题)
- [十二、IPv6 服务器代码同步（推荐）](#十二ipv6-服务器代码同步推荐)

---

## 一、部署前准备

> ⚠️ **强制要求**: 在执行任何迁移操作前，必须完成以下检查。

### 1.1 环境检查清单

```bash
# ========== 检查脚本: pre-deploy-check.sh ==========

#!/bin/bash
set -e  # 遇到错误立即退出

echo "========== 部署前检查 =========="

# 1. 检查环境变量文件
if [ ! -f .env.production ]; then
    echo "❌ 错误: .env.production 文件不存在"
    echo "请先创建: cp .env .env.production"
    exit 1
fi
echo "✅ 环境文件存在"

# 2. 检查备份目录
backup_dir="/backup/mysql"
if [ ! -d "$backup_dir" ]; then
    echo "❌ 错误: 备份目录不存在: $backup_dir"
    echo "请先创建: mkdir -p $backup_dir"
    exit 1
fi
echo "✅ 备份目录存在: $backup_dir"

# 3. 检查磁盘空间（至少需要 1GB 可用空间）
available_mb=$(df -m "$backup_dir" | awk 'NR==2 {print $4}')
if [ "$available_mb" -lt 1024 ]; then
    echo "❌ 错误: 磁盘空间不足 (当前: ${available_mb}MB, 需要: 1024MB)"
    exit 1
fi
echo "✅ 磁盘空间充足: ${available_mb}MB"

# 4. 检查 MySQL 服务
if ! systemctl is-active --quiet mysql 2>/dev/null; then
    echo "❌ 错误: MySQL 服务未运行"
    echo "请启动: systemctl start mysql"
    exit 1
fi
echo "✅ MySQL 服务运行中"

# 5. 检查 PHP 扩展
required_extensions=("pdo" "pdo_mysql" "mbstring" "json")
for ext in "${required_extensions[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        echo "❌ 错误: PHP 扩展缺失: $ext"
        exit 1
    fi
done
echo "✅ PHP 扩展完整"

echo "========== 部署前检查完成 =========="
```

### 1.2 执行检查

> **执行位置**: 在项目根目录（`/www/wwwroot/binaryecom20/Files/core`）下执行
>
> **执行方式**: 宝塔面板终端 或 SSH 终端

```bash
# 步骤 1: 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 步骤 2: 保存检查脚本
cat > pre-deploy-check.sh << 'EOF'
#!/bin/bash
set -e
echo "========== 部署前检查 =========="
if [ ! -f .env.production ]; then
    echo "❌ 错误: .env.production 文件不存在"
    exit 1
fi
echo "✅ 环境文件存在"
backup_dir="/backup/mysql"
if [ ! -d "$backup_dir" ]; then
    echo "❌ 错误: 备份目录不存在: $backup_dir"
    exit 1
fi
echo "✅ 备份目录存在"
available_mb=$(df -m "$backup_dir" | awk 'NR==2 {print $4}')
if [ "$available_mb" -lt 1024 ]; then
    echo "❌ 错误: 磁盘空间不足"
    exit 1
fi
echo "✅ 磁盘空间充足: ${available_mb}MB"
if ! systemctl is-active --quiet mysql 2>/dev/null; then
    echo "❌ 错误: MySQL 服务未运行"
    exit 1
fi
echo "✅ MySQL 服务运行中"
required_extensions=("pdo" "pdo_mysql" "mbstring" "json")
for ext in "${required_extensions[@]}"; do
    if ! php -m | grep -q "^$ext$"; then
        echo "❌ 错误: PHP 扩展缺失: $ext"
        exit 1
    fi
done
echo "✅ PHP 扩展完整"
echo "========== 部署前检查完成 =========="
EOF

chmod +x pre-deploy-check.sh
./pre-deploy-check.sh
```

---

## 二、MySQL 准备工作

> ⚠️ 以下命令需在 MySQL 服务器上执行，建议使用 root 或有 CREATE USER 权限的账号。

### 2.1 登录 MySQL

```bash
# 方式一：交互式登录（输入密码）
mysql -u root -p

# 方式二：非交互式（适合脚本，密码自行替换）
mysql -u root -p'YourRootPassword' -e "SHOW DATABASES;"
```

### 2.2 创建数据库

```sql
-- 创建数据库（指定 utf8mb4 字符集）
CREATE DATABASE binary_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 验证创建结果
SHOW CREATE DATABASE binary_db;
```

### 2.3 创建应用用户（最小权限）

```sql
-- 创建只读账号（仅给测试环境使用）
CREATE USER 'binary_readonly'@'localhost' IDENTIFIED BY 'Read0nlyP@ss';
GRANT SELECT ON binary_db.* TO 'binary_readonly'@'localhost';

-- 创建读写账号（生产环境使用）
CREATE USER 'binary_user'@'localhost' IDENTIFIED BY 'YourStrongP@ssw0rd123';
CREATE USER 'binary_user'@'%' IDENTIFIED BY 'YourStrongP@ssw0rd123';

-- 授权（只给目标库，不给全局权限）
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, LOCK TABLES
ON binary_db.* TO 'binary_user'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, LOCK TABLES
ON binary_db.* TO 'binary_user'@'%';

-- 刷新权限
FLUSH PRIVILEGES;

-- 验证权限
SHOW GRANTS FOR 'binary_user'@'localhost';
```

### 2.4 验证数据库可用

```bash
# 测试连接
mysql -u binary_user -p -e "USE binary_db; SHOW TABLES;"
```

### 2.5 创建备份目录

```bash
# 创建备份目录（确保有足够空间）
sudo mkdir -p /backup/mysql
sudo chown -R $USER:$USER /backup/mysql
sudo chmod 750 /backup/mysql

# 验证目录权限
ls -ld /backup/mysql
```

---

## 三、Laravel 配置

### 3.1 准备环境文件

```bash
# 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 复制环境文件
cp .env .env.production

# 编辑配置（必改项）
vim .env.production
```

### 3.2 关键配置项说明

```bash
# ==================== 基础配置 ====================
APP_NAME="BinaryEcom20"
APP_ENV=production              # 测试环境改为 testing
APP_DEBUG=false                 # 生产环境必须 false
APP_URL=https://yourdomain.com
APP_TIMEZONE=UTC                # 保持 UTC，与 MySQL 一致

# ==================== 数据库配置（核心） ====================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1               # 或 MySQL 服务器 IP
DB_PORT=3306
DB_DATABASE=binary_db           # 与 MySQL 创建的库名一致
DB_USERNAME=binary_user         # 与 MySQL 创建的用户名一致
DB_PASSWORD=YourStrongP@ssw0rd123  # 与 MySQL 创建的密码一致

# 字符集配置（确保 utf8mb4）
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# ==================== Redis 配置 ====================
CACHE_DRIVER=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### 3.3 配置验证

```bash
# 测试 Laravel 数据库连接
php artisan tinker --env=production
> DB::connection()->getPdo();
> exit
```

---

## 四、执行迁移

> ⚠️ **重要**: 执行迁移前必须完成数据库备份（见 4.1 节）。

### 4.1 强制备份（必须执行）

> 🔴 **强制要求**: 在执行任何迁移前，必须完成数据库备份。

```bash
# ========== 备份脚本: backup-database.sh ==========

#!/bin/bash
set -e

DB_NAME="binary_db"
DB_USER="binary_user"
backup_dir="/backup/mysql"
timestamp=$(date +%Y%m%d_%H%M%S)
backup_file="${backup_dir}/${DB_NAME}_${timestamp}.sql"

echo "========== 数据库备份 =========="

if [ ! -d "$backup_dir" ]; then
    echo "❌ 错误: 备份目录不存在: $backup_dir"
    exit 1
fi

available_mb=$(df -m "$backup_dir" | awk 'NR==2 {print $4}')
if [ "$available_mb" -lt 1024 ]; then
    echo "❌ 错误: 磁盘空间不足 (当前: ${available_mb}MB)"
    exit 1
fi
echo "✅ 磁盘空间充足: ${available_mb}MB"

echo "正在备份数据库: $DB_NAME"
echo "备份文件: $backup_file"

if mysqldump -u "$DB_USER" -p "$DB_NAME" > "$backup_file" 2>&1; then
    if [ -s "$backup_file" ]; then
        backup_size=$(du -h "$backup_file" | cut -f1)
        echo "✅ 备份成功"
        echo "备份大小: $backup_size"
        echo "备份文件: $backup_file"
        
        echo "备份时间: $(date '+%Y-%m-%d %H:%M:%S')" > "${backup_file}.info"
        echo "备份大小: $backup_size" >> "${backup_file}.info"
        echo "数据库: $DB_NAME" >> "${backup_file}.info"
        
        find "$backup_dir" -name "${DB_NAME}_*.sql" -mtime +7 -delete
        find "$backup_dir" -name "${DB_NAME}_*.sql.info" -mtime +7 -delete
        
        exit 0
    else
        echo "❌ 错误: 备份文件为空"
        rm -f "$backup_file"
        exit 1
    fi
else
    echo "❌ 备份失败"
    exit 1
fi
```

**执行备份**:

> **执行位置**: 在项目根目录（`/www/wwwroot/binaryecom20/Files/core`）下执行

```bash
# 步骤 1: 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 步骤 2: 保存备份脚本
cat > backup-database.sh << 'EOF'
#!/bin/bash
set -e
DB_NAME="binary_db"
DB_USER="binary_user"
backup_dir="/backup/mysql"
timestamp=$(date +%Y%m%d_%H%M%S)
backup_file="${backup_dir}/${DB_NAME}_${timestamp}.sql"

echo "========== 数据库备份 =========="

if [ ! -d "$backup_dir" ]; then
    echo "❌ 错误: 备份目录不存在: $backup_dir"
    exit 1
fi

available_mb=$(df -m "$backup_dir" | awk 'NR==2 {print $4}')
if [ "$available_mb" -lt 1024 ]; then
    echo "❌ 错误: 磁盘空间不足 (当前: ${available_mb}MB)"
    exit 1
fi
echo "✅ 磁盘空间充足: ${available_mb}MB"

echo "正在备份数据库: $DB_NAME"
echo "备份文件: $backup_file"

if mysqldump -u "$DB_USER" -p "$DB_NAME" > "$backup_file" 2>&1; then
    if [ -s "$backup_file" ]; then
        backup_size=$(du -h "$backup_file" | cut -f1)
        echo "✅ 备份成功"
        echo "备份大小: $backup_size"
        echo "备份文件: $backup_file"

        echo "备份时间: $(date '+%Y-%m-%d %H:%M:%S')" > "${backup_file}.info"
        echo "备份大小: $backup_size" >> "${backup_file}.info"
        echo "数据库: $DB_NAME" >> "${backup_file}.info"

        find "$backup_dir" -name "${DB_NAME}_*.sql" -mtime +7 -delete
        find "$backup_dir" -name "${DB_NAME}_*.sql.info" -mtime +7 -delete

        exit 0
    else
        echo "❌ 错误: 备份文件为空"
        rm -f "$backup_file"
        exit 1
    fi
else
    echo "❌ 备份失败"
    exit 1
fi
EOF

chmod +x backup-database.sh
./backup-database.sh
```

### 4.2 大表操作评估

> ⚠️ **重要**: 在执行包含 ALTER TABLE 的迁移前，必须评估表大小。

```bash
# ========== 表大小评估脚本: assess-tables.sh ==========

#!/bin/bash

DB_NAME="binary_db"
DB_USER="binary_user"

echo "========== 评估表大小 =========="

mysql -u "$DB_USER" -p "$DB_NAME" -e "
SELECT 
    TABLE_NAME as '表名',
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS '大小(MB)',
    TABLE_ROWS as '行数',
    CASE 
        WHEN (data_length + index_length) > 100*1024*1024 THEN '⚠️ 大表 (>100MB)'
        WHEN (data_length + index_length) > 10*1024*1024 THEN '⚡ 中表 (>10MB)'
        ELSE '✅ 小表'
    END AS '风险等级'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = '$DB_NAME'
ORDER BY (data_length + index_length) DESC;
"

echo ""
echo "========== 评估建议 =========="
echo "✅ 小表 (< 10MB): 可以直接执行 ALTER"
echo "⚡ 中表 (10-100MB): 建议在低峰期执行"
echo "⚠️ 大表 (> 100MB): 强烈建议使用 pt-online-schema-change"
```

**执行评估**:

> **执行位置**: 在项目根目录（`/www/wwwroot/binaryecom20/Files/core`）下执行

```bash
# 步骤 1: 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 步骤 2: 保存并执行评估脚本
chmod +x assess-tables.sh
./assess-tables.sh
```

**针对大表的解决方案**:

```bash
# 如果 products 表 > 100MB，使用 pt-online-schema-change
# 安装工具
sudo apt-get install percona-toolkit

# 在线修改表结构（不锁表）
pt-online-schema-change \
  --alter "MODIFY COLUMN description LONGTEXT DEFAULT NULL" \
  --user=binary_user \
  --password \
  --host=localhost \
  D=binary_db,t=products \
  --execute
```

### 4.3 迁移文件清单

本项目共有 **约 51 个迁移文件**，主要包含：

| 模块 | 迁移文件 | 说明 |
|------|----------|------|
| 用户系统 | `0001_01_01_000000_create_users_table.php` | 用户表 |
| | `2025_12_18_000010_add_v101_user_fields.php` | ⚠️ 危险：ALTER 多字段 |
| 结算系统 | `2025_12_18_000002_create_weekly_settlements_table.php` | 周结算 |
| | `2025_12_18_000003_create_weekly_settlement_user_summaries_table.php` | 用户结算汇总 |
| | `2025_12_18_000004_create_quarterly_settlements_table.php` | 季度结算 |
| PV 账本 | `2025_12_18_000000_create_pv_ledger_table.php` | PV 流水 |
| | `2025_12_25_000000_add_details_to_pv_ledger_table.php` | 添加 details 字段 |
| 待发奖金 | `2025_12_18_000001_create_pending_bonuses_table.php` | 待发奖金表 |
| 调整记录 | `2025_12_18_000006_create_adjustment_batches_table.php` | 调整批次 |
| | `2025_12_18_000007_create_adjustment_entries_table.php` | 调整明细 |
| 索引优化 | `2025_12_19_210000_optimize_database_indexes.php` | ⚠️ 危险：DROP+CREATE INDEX |
| | `2025_12_23_024635_optimize_database_indexes.php` | ⚠️ 危险：DROP+CREATE INDEX |
| 商品描述 | `2025_12_25_100000_expand_product_description_field.php` | ⚠️ 危险：LONGTEXT 转换 |

### 4.4 分阶段执行迁移

> ⚠️ **推荐**: 将迁移分为三个阶段执行，降低风险。

**阶段划分**:
- **阶段 1**: 创建表（安全，可随时执行）
- **阶段 2**: 添加字段（中等风险，需评估表大小）
- **阶段 3**: 索引优化（高风险，必须在低峰期执行）

### 4.5 执行迁移

```bash
# ========== 步骤 1: 预演模式（先看不执行） ==========
php artisan migrate --pretend --env=production

# ========== 步骤 2: 分阶段执行 ==========

# 阶段 1: 创建表（安全）
echo "========== 阶段 1: 创建表 =========="
php artisan migrate --path=database/migrations/0001_01_01_*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_18_00000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_18_00001[4-9].php --force --env=production
php artisan migrate --path=database/migrations/2025_12_18_00002*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_19_000024*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_20_000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_20_100000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_20_110000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_20_120000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_20_120100*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_20_120200*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_20_120300*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_20_190542*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_22_*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_23_120000*.php --force --env=production

# 验证阶段 1
php artisan migrate:status --env=production

# 确认继续
read -p "阶段 1 完成，是否继续阶段 2? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已暂停，请检查后手动继续"
    exit 0
fi

# 阶段 2: 添加字段（中等风险）
echo "========== 阶段 2: 添加字段 =========="
php artisan migrate --path=database/migrations/2025_12_18_000010*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_18_000011*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_18_000012*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_18_000015*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_18_000018*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_18_000023*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_24_100000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_25_000000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_25_000001*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_25_020000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_25_100000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_26_000001*.php --force --env=production

# 验证阶段 2
php artisan migrate:status --env=production

# 确认继续
read -p "阶段 2 完成，是否继续阶段 3 (索引优化)? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已暂停，请在低峰期手动执行阶段 3"
    exit 0
fi

# 阶段 3: 索引优化（高风险，建议低峰期执行）
echo "========== 阶段 3: 索引优化 =========="
echo "⚠️ 警告: 索引优化可能需要较长时间，建议在业务低峰期执行"
read -p "确认继续? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已取消"
    exit 0
fi

php artisan migrate --path=database/migrations/2025_12_19_210000*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_23_024635*.php --force --env=production
php artisan migrate --path=database/migrations/2025_12_24_000000*.php --force --env=production

# ========== 步骤 3: 查看迁移状态 ==========
php artisan migrate:status --env=production
```

### 4.6 记录迁移日志

```bash
# 记录迁移执行日志
log_file="storage/logs/migration_$(date +%Y%m%d_%H%M%S).log"
php artisan migrate --force --env=production 2>&1 | tee "$log_file"

echo "迁移日志已保存: $log_file"
```

### 4.7 验证表结构

```bash
# 查看所有表
mysql -u binary_user -p binary_db -e "SHOW TABLES;"

# 查看关键表结构
mysql -u binary_user -p binary_db -e "DESCRIBE users;"
mysql -u binary_user -p binary_db -e "DESCRIBE pv_ledger;"
mysql -u binary_user -p binary_db -e "DESCRIBE weekly_settlements;"
```

### 4.8 字符集一致性验证

```bash
# ========== 字符集验证脚本 ==========

#!/bin/bash

DB_NAME="binary_db"
DB_USER="binary_user"

echo "========== 验证字符集 =========="

mysql -u "$DB_USER" -p "$DB_NAME" -e "
SELECT 
    TABLE_NAME as '表名',
    TABLE_COLLATION as '字符集排序规则',
    CASE 
        WHEN TABLE_COLLATION != 'utf8mb4_unicode_ci' THEN '⚠️ 需要修正'
        ELSE '✅ 正确'
    END AS '状态'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = '$DB_NAME'
ORDER BY TABLE_NAME;
"

# 检查是否有非 utf8mb4 的表
non_utf8_count=$(mysql -u "$DB_USER" -p "$DB_NAME" -sN -e "
SELECT COUNT(*) FROM information_schema.TABLES
WHERE TABLE_SCHEMA = '$DB_NAME'
AND TABLE_COLLATION != 'utf8mb4_unicode_ci';
")

if [ "$non_utf8_count" -gt 0 ]; then
    echo ""
    echo "⚠️ 警告: 发现 $non_utf8_count 个表未使用 utf8mb4_unicode_ci"
    echo "修正命令示例:"
    echo "ALTER TABLE table_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
else
    echo ""
    echo "✅ 所有表字符集正确"
fi
```

---

## 五、数据填充（可选）

### 5.1 可用 Seeder

本项目 `database/seeders/` 目录包含：

| Seeder | 说明 | 是否建议生产执行 |
|--------|------|------------------|
| `PermissionSeeder.php` | 权限配置 | ✅ 是 |

### 5.2 执行 Seeder

```bash
# 执行权限填充
php artisan db:seed --class=PermissionSeeder --env=production

# 或执行所有 Seeder（注意：可能包含测试数据）
php artisan db:seed --env=production
```

### 5.3 禁止执行的 Seeder

```
❌ 任何包含真实用户数据的 Seeder
❌ 包含交易记录的 Seeder
❌ 包含敏感配置的 Seeder（需手动审核）
```

---

## 六、危险迁移识别

> ⚠️ **重要**: 以下迁移包含危险操作，执行前必须在测试环境验证。

### 6.1 危险操作清单（已更新）

以下迁移包含 **危险操作**，执行前必须在测试环境验证：

| 迁移文件 | 危险操作 | 影响范围 | 建议 |
|----------|----------|----------|------|
| `2025_12_18_000010_add_v101_user_fields.php` | ALTER 多字段 | users 表 | ⚠️ 需停机窗口 |
| `2025_12_18_000011_add_source_fields_to_transactions_table.php` | ALTER | transactions 表 | ⚠️ 大表风险 |
| `2025_12_19_210000_optimize_database_indexes.php` | DROP + CREATE INDEX | 多表 | ✅ 相对安全 |
| `2025_12_23_024635_optimize_database_indexes.php` | DROP + CREATE INDEX | 多表 | ✅ 相对安全 |
| `2025_12_25_100000_expand_product_description_field.php` | MODIFY COLUMN | products 表 | ⚠️ 大表风险，需评估 |
| `2025_12_24_100000_alter_frontends_data_values.php` | MODIFY COLUMN | frontends 表 | ⚠️ 数据类型转换 |

### 6.2 危险迁移处理流程

```bash
# ========== 危险迁移处理流程 ==========

# 步骤 1: 在测试环境执行
echo "在测试环境执行迁移..."
php artisan migrate --path=database/migrations/2025_12_25_100000_expand_product_description_field.php --env=testing

# 步骤 2: 验证数据完整性
php artisan tinker --env=testing
> App\Models\Product::count();
> App\Models\Product::where('description', '!=', '')->count();
> exit

# 步骤 3: 评估生产环境表大小
./assess-tables.sh

# 步骤 4: 根据表大小决定执行方式
# - 小表 (< 10MB): 直接执行
# - 中表 (10-100MB): 低峰期执行
# - 大表 (> 100MB): 使用 pt-online-schema-change

# 步骤 5: 记录执行时间
echo "迁移开始时间: $(date '+%Y-%m-%d %H:%M:%S')" > migration-timing.log
php artisan migrate --path=database/migrations/2025_12_25_100000_expand_product_description_field.php --force --env=production
echo "迁移结束时间: $(date '+%Y-%m-%d %H:%M:%S')" >> migration-timing.log
```

### 6.3 危险迁移扫描命令

```bash
# 自动扫描危险操作
grep -rn "DROP\|ALTER.*COLUMN\|DROP COLUMN\|rename_column\|change\|MODIFY COLUMN" \
  database/migrations/*.php | grep -v "CREATE\|ADD COLUMN\|ADD INDEX"
```

---

## 七、回滚策略

### 7.1 标准回滚（有限制）

```bash
# 回滚最后一批迁移
php artisan migrate:rollback --env=production

# 回滚多批（指定步数）
php artisan migrate:rollback --step=2 --env=production

# 查看回滚状态
php artisan migrate:status --env=production
```

### 7.2 回滚限制

```
⚠️ 注意：migrate:rollback 有以下限制
1. 只能回滚最近的迁移
2. DROP TABLE 无法恢复
3. ALTER COLUMN 可能丢失数据
4. 已执行的 seeders 不会自动回滚
```

### 7.3 完整回滚流程（推荐）

```bash
# ========== 步骤 1：迁移前备份 ==========
mysqldump -u binary_user -p binary_db > /backup/mysql/binary_db_$(date +%Y%m%d_%H%M%S).sql

# ========== 步骤 2：发现问题，执行回滚 ==========
php artisan migrate:rollback --env=production

# ========== 步骤 3：验证回滚结果 ==========
php artisan migrate:status --env=production

# ========== 步骤 4：如需完全恢复，使用备份 ==========
mysql -u binary_user -p binary_db < /backup/mysql/binary_db_20251225_120000.sql
```

### 7.4 自动化恢复脚本

```bash
# ========== 恢复脚本: restore-database.sh ==========

#!/bin/bash
set -e

# 检查参数
if [ -z "$1" ]; then
    echo "❌ 错误: 请指定备份文件路径"
    echo "用法: ./restore-database.sh <备份文件路径>"
    echo "示例: ./restore-database.sh /backup/mysql/binary_db_20251226_120000.sql"
    exit 1
fi

backup_file="$1"
db_name="binary_db"
db_user="binary_user"

echo "========== 数据库恢复 =========="
echo "⚠️  警告: 此操作将覆盖当前数据库"
read -p "确认恢复? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已取消恢复"
    exit 0
fi

# 验证备份文件
if [ ! -f "$backup_file" ]; then
    echo "❌ 错误: 备份文件不存在: $backup_file"
    exit 1
fi

if [ ! -s "$backup_file" ]; then
    echo "❌ 错误: 备份文件为空"
    exit 1
fi

# 显示备份信息
if [ -f "${backup_file}.info" ]; then
    echo "备份信息:"
    cat "${backup_file}.info"
fi

# 执行恢复
echo "正在恢复数据库..."
if mysql -u "$db_user" -p "$db_name" < "$backup_file" 2>&1; then
    echo "✅ 数据库恢复成功"
    
    # 清理缓存
    php artisan cache:clear --env=production
    php artisan config:clear --env=production
    php artisan route:clear --env=production
    
    echo "✅ 缓存已清理"
else
    echo "❌ 数据库恢复失败"
    exit 1
fi
```

**使用恢复脚本**:

> **执行位置**: 在项目根目录（`/www/wwwroot/binaryecom20/Files/core`）下执行

```bash
# 步骤 1: 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 步骤 2: 保存恢复脚本
cat > restore-database.sh << 'EOF'
#!/bin/bash
set -e

# 检查参数
if [ -z "$1" ]; then
    echo "❌ 错误: 请指定备份文件路径"
    echo "用法: ./restore-database.sh <备份文件路径>"
    echo "示例: ./restore-database.sh /backup/mysql/binary_db_20251226_120000.sql"
    exit 1
fi
backup_file="$1"
db_name="binary_db"
db_user="binary_user"

echo "========== 数据库恢复 =========="
echo "⚠️  警告: 此操作将覆盖当前数据库"
read -p "确认恢复? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已取消恢复"
    exit 0
fi
if [ ! -f "$backup_file" ]; then
    echo "❌ 错误: 备份文件不存在: $backup_file"
    exit 1
fi

if [ ! -s "$backup_file" ]; then
    echo "❌ 错误: 备份文件为空"
    exit 1
fi

if [ -f "${backup_file}.info" ]; then
    echo "备份信息:"
    cat "${backup_file}.info"
fi

echo "正在恢复数据库..."
if mysql -u "$db_user" -p "$db_name" < "$backup_file" 2>&1; then
    echo "✅ 数据库恢复成功"

    php artisan cache:clear --env=production
    php artisan config:clear --env=production
    php artisan route:clear --env=production
    echo "✅ 缓存已清理"
else
    echo "❌ 数据库恢复失败"
    exit 1
fi
EOF

chmod +x restore-database.sh

# 列出可用备份
ls -lh /backup/mysql/binary_db_*.sql

# 恢复指定备份
./restore-database.sh /backup/mysql/binary_db_20251226_120000.sql
```

### 7.5 补偿脚本（高级）

对于危险迁移，建议创建补偿脚本：

```bash
# 创建补偿迁移
php artisan make:migration compensate_20251225_product_description

# database/migrations/xxxx_xx_xx_xxxxxx_compensate_product_description.php
<?php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void {
        // 补偿操作（如：数据修正）
    }

    public function down(): void {
        // 回滚补偿操作
    }
};
```

---

## 八、健康检查

> ✅ **新增**: 迁移完成后执行健康检查，确保系统正常。

### 8.1 数据库健康检查脚本

```bash
# ========== 健康检查脚本: health-check.sh ==========

#!/bin/bash

DB_NAME="binary_db"
DB_USER="binary_user"

echo "========== 数据库健康检查 =========="

# 1. 检查表数量
table_count=$(mysql -u "$DB_USER" -p "$DB_NAME" -sN -e "SHOW TABLES;" | wc -l)
if [ $table_count -lt 40 ]; then
    echo "❌ 错误: 表数量不足 (当前: $table_count, 期望: 40+)"
    exit 1
else
    echo "✅ 表数量正常: $table_count"
fi

# 2. 检查关键表
critical_tables=("users" "pv_ledger" "weekly_settlements" "transactions" "products" "orders")
for table in "${critical_tables[@]}"; do
    if mysql -u "$DB_USER" -p "$DB_NAME" -e "DESCRIBE $table" > /dev/null 2>&1; then
        echo "✅ 关键表存在: $table"
    else
        echo "❌ 错误: 关键表不存在: $table"
        exit 1
    fi
done

# 3. 检查字符集
non_utf8=$(mysql -u "$DB_USER" -p "$DB_NAME" -sN -e "
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = '$DB_NAME'
    AND TABLE_COLLATION != 'utf8mb4_unicode_ci';
")
if [ "$non_utf8" -gt 0 ]; then
    echo "⚠️  警告: $non_utf8 个表未使用 utf8mb4"
else
    echo "✅ 所有表字符集正确"
fi

# 4. 检查迁移状态
echo ""
echo "========== 迁移状态 =========="
php artisan migrate:status --env=production

# 5. 检查数据库连接
echo ""
echo "========== 数据库连接测试 =========="
php artisan tinker --env=production --execute="
    try {
        DB::connection()->getPdo();
        echo '✅ 数据库连接正常\n';
    } catch (\Exception \$e) {
        echo '❌ 数据库连接失败: ' . \$e->getMessage() . '\n';
        exit(1);
    }
"

echo ""
echo "========== 健康检查完成 =========="
```

### 8.2 应用健康检查

> **执行位置**: 在项目根目录（`/www/wwwroot/binaryecom20/Files/core`）下执行

```bash
# 步骤 1: 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 步骤 2: 检查应用路由
php artisan route:list --env=production | head -20

# 检查缓存配置
php artisan config:cache --env=production
php artisan route:cache --env=production

# 验证关键服务
php artisan tinker --env=production --execute="
    echo '用户服务: ' . (class_exists(App\Services\UserService::class) ? '✅' : '❌') . '\n';
    echo '结算服务: ' . (class_exists(App\Services\SettlementService::class) ? '✅' : '❌') . '\n';
    echo 'PV账本服务: ' . (class_exists(App\Services\PVLedgerService::class) ? '✅' : '❌') . '\n';
"
```

---

## 九、上线前检查清单

> ✅ **新增**: 部署前必须完成的所有检查项。

### 9.1 数据库检查

- [ ] 备份已完成（`./backup-database.sh`）
- [ ] 表数量正确（40+ 表）
- [ ] 所有表使用 utf8mb4 字符集
- [ ] 关键表存在（users, pv_ledger, weekly_settlements, transactions, products, orders）
- [ ] 迁移状态全部为 "Yes"

### 9.2 配置检查

- [ ] `.env.production` 已配置
- [ ] `APP_DEBUG=false`
- [ ] 数据库连接测试通过
- [ ] Redis 连接正常
- [ ] 时区设置为 UTC

### 9.3 安全检查

- [ ] 数据库用户权限最小化
- [ ] 备份目录权限正确（750）
- [ ] 敏感文件不可公开访问
- [ ] SSL 证书已配置

### 9.4 性能检查

- [ ] 缓存已启用（Redis）
- [ ] 队列配置正确
- [ ] 定时任务已配置（Cron）
- [ ] OPcache 已启用

### 9.5 监控检查

- [ ] 日志目录可写
- [ ] 错误日志监控已配置
- [ ] 性能监控已配置
- [ ] 备份自动清理已配置（7天保留）

---

## 十、部署脚本

> ✅ **新增**: 一键部署脚本，整合所有步骤。

### 10.1 完整部署脚本

```bash
# ========== 完整部署脚本: deploy-all.sh ==========

#!/bin/bash
set -e  # 遇到错误立即退出

echo "========== BinaryEcom20 数据库部署 =========="
echo "开始时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# 步骤 1: 部署前检查
echo "========== 步骤 1: 部署前检查 =========="
if [ -f pre-deploy-check.sh ]; then
    ./pre-deploy-check.sh
else
    echo "❌ 错误: pre-deploy-check.sh 不存在"
    exit 1
fi

# 步骤 2: 数据库备份
echo ""
echo "========== 步骤 2: 数据库备份 =========="
if [ -f backup-database.sh ]; then
    ./backup-database.sh
else
    echo "❌ 错误: backup-database.sh 不存在"
    exit 1
fi

# 步骤 3: 评估表大小
echo ""
echo "========== 步骤 3: 评估表大小 =========="
if [ -f assess-tables.sh ]; then
    ./assess-tables.sh
else
    echo "⚠️  警告: assess-tables.sh 不存在，跳过评估"
fi

# 步骤 4: 执行迁移
echo ""
echo "========== 步骤 4: 执行迁移 =========="
read -p "是否继续执行迁移? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已取消部署"
    exit 0
fi

# 预演模式
echo "预演模式..."
php artisan migrate --pretend --env=production

# 确认执行
read -p "预演完成，是否执行实际迁移? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已取消部署"
    exit 0
fi

# 执行迁移
log_file="storage/logs/migration_$(date +%Y%m%d_%H%M%S).log"
php artisan migrate --force --env=production 2>&1 | tee "$log_file"

# 步骤 5: 健康检查
echo ""
echo "========== 步骤 5: 健康检查 =========="
if [ -f health-check.sh ]; then
    ./health-check.sh
else
    echo "⚠️  警告: health-check.sh 不存在，跳过健康检查"
fi

# 完成
echo ""
echo "========== 部署完成 =========="
echo "结束时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo "迁移日志: $log_file"
echo ""
echo "✅ 部署成功！"
```

### 10.2 使用部署脚本

> **执行位置**: 在项目根目录（`/www/wwwroot/binaryecom20/Files/core`）下执行

```bash
# 步骤 1: 进入项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 步骤 2: 保存部署脚本
cat > deploy-all.sh << 'EOF'
#!/bin/bash
set -e
echo "========== BinaryEcom20 数据库部署 =========="
echo "开始时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
echo "========== 步骤 1: 部署前检查 =========="
if [ -f pre-deploy-check.sh ]; then
    ./pre-deploy-check.sh
else
    echo "❌ 错误: pre-deploy-check.sh 不存在"
    exit 1
fi
echo ""
echo "========== 步骤 2: 数据库备份 =========="
if [ -f backup-database.sh ]; then
    ./backup-database.sh
else
    echo "❌ 错误: backup-database.sh 不存在"
    exit 1
fi
echo ""
echo "========== 步骤 3: 评估表大小 =========="
if [ -f assess-tables.sh ]; then
    ./assess-tables.sh
fi
echo ""
echo "========== 步骤 4: 执行迁移 =========="
read -p "是否继续执行迁移? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已取消部署"
    exit 0
fi
php artisan migrate --pretend --env=production
read -p "预演完成，是否执行实际迁移? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo "已取消部署"
    exit 0
fi
log_file="storage/logs/migration_$(date +%Y%m%d_%H%M%S).log"
php artisan migrate --force --env=production 2>&1 | tee "$log_file"
echo ""
echo "========== 步骤 5: 健康检查 =========="
if [ -f health-check.sh ]; then
    ./health-check.sh
fi
echo ""
echo "========== 部署完成 =========="
echo "结束时间: $(date '+%Y-%m-%d %H:%M:%S')"
echo "迁移日志: $log_file"
echo ""
echo "✅ 部署成功！"
EOF

chmod +x deploy-all.sh

# 执行部署
./deploy-all.sh
```

---

## 十一、常见问题

### 11.1 迁移失败

**问题**: 迁移执行失败，提示 SQL 错误

**解决方案**:
```bash
# 1. 查看详细错误
php artisan migrate --force --env=production

# 2. 检查迁移状态
php artisan migrate:status --env=production

# 3. 回滚失败的迁移
php artisan migrate:rollback --env=production

# 4. 修复问题后重新执行
php artisan migrate --force --env=production
```

### 11.2 字符集问题

**问题**: 表或字段使用错误的字符集

**解决方案**:
```sql
-- 修正单个表
ALTER TABLE table_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 批量修正所有表
SELECT CONCAT('ALTER TABLE ', table_name, ' CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'binary_db'
AND TABLE_COLLATION != 'utf8mb4_unicode_ci';
```

### 11.3 大表操作超时

**问题**: ALTER TABLE 操作超时

**解决方案**:
```bash
# 方案 1: 使用 pt-online-schema-change
pt-online-schema-change \
  --alter="你的ALTER语句" \
  --user=binary_user \
  --password \
  --host=localhost \
  D=binary_db,t=table_name \
  --execute

# 方案 2: 分批执行（适用于数据更新）
-- 创建新表
CREATE TABLE new_table LIKE old_table;

-- 分批复制数据
INSERT INTO new_table SELECT * FROM old_table LIMIT 10000;

-- 重命名表
RENAME TABLE old_table TO old_table_backup, new_table TO old_table;
```

### 11.4 权限问题

**问题**: 数据库用户权限不足

**解决方案**:
```sql
-- 检查当前权限
SHOW GRANTS FOR 'binary_user'@'localhost';

-- 添加缺失权限
GRANT ALTER, CREATE, DROP, INDEX ON binary_db.* TO 'binary_user'@'localhost';
FLUSH PRIVILEGES;
```

### 11.5 备份恢复失败

**问题**: 备份文件无法恢复

**解决方案**:
```bash
# 1. 验证备份文件完整性
gzip -t backup_file.sql.gz

# 2. 检查备份文件内容
head -100 backup_file.sql

# 3. 尝试恢复（忽略错误）
mysql -u binary_user -p binary_db < backup_file.sql 2>&1 | tee restore.log

# 4. 如果恢复失败，从最近的可用备份恢复
```

---

## 十二、IPv6 服务器代码同步（推荐）

> 适用场景：云服务器只有 IPv6，无法从 GitHub 直接 `git pull`。

### 12.1 方案 A（推荐）：推送式部署（不需要服务器访问 GitHub）

**步骤 0：确保本地能 SSH 登录云服务器**

```bash
ssh root@[你的IPv6地址]
```

**步骤 1：在云服务器创建裸仓库**

```bash
mkdir -p /www/wwwroot/repos
cd /www/wwwroot/repos
git init --bare binaryecom20.git
```

**步骤 2：配置 post-receive 自动更新工作区**

```bash
cat > /www/wwwroot/repos/binaryecom20.git/hooks/post-receive << 'EOF'
#!/bin/bash
set -e

GIT_DIR="/www/wwwroot/repos/binaryecom20.git"
WORK_TREE="/www/wwwroot/binaryecom20/Files/core"
branch="refs/heads/master"

while read oldrev newrev ref; do
  if [ "$ref" = "$branch" ]; then
    git --work-tree="$WORK_TREE" --git-dir="$GIT_DIR" checkout -f master
  fi
done
EOF

chmod +x /www/wwwroot/repos/binaryecom20.git/hooks/post-receive
```

**步骤 3：在本地电脑添加远程并推送**

```bash
cd /www/wwwroot/binaryecom20/Files/core
git remote add cloud ssh://root@[你的IPv6地址]:22/www/wwwroot/repos/binaryecom20.git
git push cloud master
```

> 如果你的分支是 `main`，把上面的 `master` 替换为 `main`。

### 12.2 方案 B（备选）：镜像仓库（Gitee）+ 云服务器拉取

1. 在 Gitee 导入 GitHub 仓库并开启镜像同步  
2. 云服务器只从 Gitee `git pull`（IPv6 可达时效果最好）

---

## 附录

### A. 参考文档

- [Laravel 迁移文档](https://laravel.com/docs/migrations)
- [MySQL 字符集指南](https://dev.mysql.com/doc/refman/8.0/en/charset.html)
- [Percona Toolkit 文档](https://docs.percona.com/percona-toolkit/)

### B. 联系支持

如遇到问题，请联系技术支持并提供以下信息：

1. 错误日志（`storage/logs/laravel.log`）
2. 迁移日志（`storage/logs/migration_*.log`）
3. MySQL 错误日志
4. 系统环境信息

---

**文档版本**: v2.0
**最后更新**: 2025-12-26
**维护者**: BinaryEcom20 开发团队
