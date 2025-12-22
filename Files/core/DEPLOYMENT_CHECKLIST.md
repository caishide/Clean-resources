# Laravel 生产部署清单

**项目：** BinaryEcom20
**创建时间：** 2025-12-21 11:40:00 UTC
**版本：** v1.0
**环境：** 生产环境 (裸机部署)

---

## 📋 部署概览

本清单涵盖从服务器准备到生产上线的完整流程，确保应用安全、稳定、高效运行。

### 部署架构
```
[Load Balancer] → [Web Server: Nginx] → [PHP-FPM] → [Laravel App]
                                       ↓
                                  [Redis Cache]
                                       ↓
                                 [MySQL Database]
```

### 服务器规格建议
```
CPU: 4核心+
内存: 8GB+
存储: 100GB+ SSD
带宽: 10Mbps+
操作系统: Ubuntu 22.04 LTS
```

---

## 🔧 第一阶段：服务器准备

### 1.1 系统更新与安全
```bash
# 更新系统
sudo apt update && sudo apt upgrade -y

# 安装基础工具
sudo apt install -y curl wget git unzip vim htop net-tools

# 配置防火墙
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable

# 创建应用用户
sudo adduser deploy
sudo usermod -aG sudo deploy
sudo mkdir -p /home/deploy/.ssh
sudo chmod 700 /home/deploy/.ssh
```

### 1.2 安装 PHP 8.3
```bash
# 添加 PHP 仓库
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# 安装 PHP 及扩展
sudo apt install -y \
    php8.3 \
    php8.3-fpm \
    php8.3-cli \
    php8.3-common \
    php8.3-mysql \
    php8.3-xml \
    php8.3-curl \
    php8.3-gd \
    php8.3-mbstring \
    php8.3-zip \
    php8.3-bcmath \
    php8.3-redis \
    php8.3-intl \
    php8.3-readline \
    php8.3-tokenizer

# 验证安装
php -v
php -m | grep -E "mysql|redis|curl|gd|mbstring"
```

### 1.3 安装 Nginx
```bash
# 安装 Nginx
sudo apt install -y nginx

# 启动并启用
sudo systemctl start nginx
sudo systemctl enable nginx

# 检查状态
sudo systemctl status nginx
```

### 1.4 安装 MySQL 8.0
```bash
# 安装 MySQL
sudo apt install -y mysql-server

# 安全配置
sudo mysql_secure_installation

# 验证安装
sudo mysql -u root -p
```

**MySQL 配置：/etc/mysql/mysql.conf.d/mysqld.cnf**
```ini
[mysqld]
# 基础配置
bind-address = 127.0.0.1
port = 3306

# 性能优化
innodb_buffer_pool_size = 2G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_file_per_table = 1

# 查询缓存
query_cache_type = 1
query_cache_size = 64M
query_cache_limit = 2M

# 连接数
max_connections = 200

# 慢查询日志
slow_query_log = 1
slow_query_log_file = /var/log/mysql/mysql-slow.log
long_query_time = 2

# 重启 MySQL
sudo systemctl restart mysql
```

### 1.5 安装 Redis
```bash
# 安装 Redis
sudo apt install -y redis-server

# 配置 Redis
sudo sed -i 's/supervised no/supervised systemd/' /etc/redis/redis.conf
sudo systemctl enable redis-server
sudo systemctl start redis-server

# 测试
redis-cli ping
# 应返回：PONG
```

---

## 📦 第二阶段：应用部署

### 2.1 部署代码
```bash
# 切换到应用用户
sudo su - deploy

# 克隆代码 (使用实际仓库地址)
cd /var/www
sudo git clone https://github.com/your-repo/binaryecom20.git
sudo chown -R deploy:deploy binaryecom20

cd binaryecom20/Files/core

# 安装依赖
composer install --optimize-autoloader --no-dev

# 复制环境配置
cp .env.production.example .env
vim .env  # 编辑配置

# 生成应用密钥
php artisan key:generate
```

### 2.2 配置环境变量

**文件：.env**
```bash
# 应用配置
APP_NAME="BinaryEcom20"
APP_ENV=production
APP_KEY=base64:YOUR_GENERATED_KEY
APP_DEBUG=false
APP_URL=https://yourdomain.com
APP_TIMEZONE=UTC

# 数据库配置
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=binary_db
DB_USERNAME=binary_user
DB_PASSWORD=your_secure_password

# 缓存配置
CACHE_DRIVER=redis
CACHE_PREFIX=bc20_prod
CACHE_TTL=3600

# Session 配置
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
SESSION_DOMAIN=yourdomain.com

# Redis 配置
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=your_redis_password
REDIS_PORT=6379

# 队列配置
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database

# 日志配置
LOG_CHANNEL=stack
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=null

# 邮件配置
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_email_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"

# 文件系统
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket
AWS_USE_PATH_STYLE_ENDPOINT=false

# 安全配置
FORCE_HTTPS=true
TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16

# API 配置
API_PREFIX=api
API_VERSION=v1
API_RATE_LIMIT=60

# 监控配置
TELESCOPE_ENABLED=false
DEBUGBAR_ENABLED=false
SENTRY_LARAVEL_DSN=your_sentry_dsn
SENTRY_TRACES_SAMPLE_RATE=0.1

# 支付网关 (生产密钥)
STRIPE_KEY=pk_live_...
STRIPE_SECRET=sk_live_...
RAZORPAY_KEY=rzp_live_...
RAZORPAY_SECRET=...
```

### 2.3 数据库迁移与初始化
```bash
# 创建数据库
mysql -u root -p
> CREATE DATABASE binary_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> CREATE USER 'binary_user'@'localhost' IDENTIFIED BY 'your_secure_password';
> GRANT ALL PRIVILEGES ON binary_db.* TO 'binary_user'@'localhost';
> FLUSH PRIVILEGES;
> EXIT;

# 运行迁移
php artisan migrate --force

# 缓存迁移状态
php artisan migrate:status

# 填充基础数据 (可选)
php artisan db:seed --force

# 清理缓存
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 2.4 设置文件权限
```bash
# 设置所有者
sudo chown -R deploy:deploy /var/www/binaryecom20

# 设置权限
sudo find /var/www/binaryecom20 -type f -exec chmod 644 {} \;
sudo find /var/www/binaryecom20 -type d -exec chmod 755 {} \;

# 特殊权限
sudo chmod -R 775 /var/www/binaryecom20/storage
sudo chmod -R 775 /var/www/binaryecom20/bootstrap/cache

# 创建符号链接
php artisan storage:link
```

---

## 🌐 第三阶段：Web 服务器配置

### 3.1 Nginx 配置

**文件：/etc/nginx/sites-available/binaryecom20**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/binaryecom20/Files/core/public;

    # SSL 配置
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # 安全头
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # 日志
    access_log /var/log/nginx/binaryecom20_access.log;
    error_log /var/log/nginx/binaryecom20_error.log;

    # 主配置
    index index.php;

    charset utf-8;

    # 客户端配置
    client_max_body_size 16M;
    client_body_timeout 60;
    client_header_timeout 60;
    keepalive_timeout 65;

    # Laravel 路由
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 300;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # 禁止访问隐藏文件
    location ~ /\. {
        deny all;
    }

    # 禁止访问敏感文件
    location ~* \.(env|log|sql|md)$ {
        deny all;
    }

    # Gzip 压缩
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;
}
```

**启用站点：**
```bash
# 创建符号链接
sudo ln -s /etc/nginx/sites-available/binaryecom20 /etc/nginx/sites-enabled/

# 测试配置
sudo nginx -t

# 重启 Nginx
sudo systemctl restart nginx
sudo systemctl enable nginx
```

### 3.2 PHP-FPM 配置

**文件：/etc/php/8.3/fpm/pool.d/www.conf**
```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

# 进程管理
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500

# 慢日志
slowlog = /var/log/php8.3-fpm-slow.log
request_slowlog_timeout = 5s

# 超时
request_terminate_timeout = 300s
rlimit_files = 1024
rlimit_core = 0

# 环境变量
env[HOSTNAME] = $HOSTNAME
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp
```

**PHP 配置：/etc/php/8.3/fpm/php.ini**
```ini
; 基础配置
memory_limit = 256M
max_execution_time = 60
max_input_time = 60
post_max_size = 16M
upload_max_filesize = 16M
max_file_uploads = 20

; OPcache
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
opcache.save_comments = 1
opcache.fast_shutdown = 1

; 日志
log_errors = On
error_log = /var/log/php8.3-fpm_errors.log

; 安全
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
```

**重启 PHP-FPM：**
```bash
sudo systemctl restart php8.3-fpm
sudo systemctl enable php8.3-fpm
```

---

## 🔐 第四阶段：SSL 证书

### 4.1 安装 Certbot
```bash
# 安装 Certbot
sudo apt install -y certbot python3-certbot-nginx

# 获取证书
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# 自动续期
sudo crontab -e
# 添加：
0 12 * * * /usr/bin/certbot renew --quiet
```

---

## 📊 第五阶段：监控与告警

### 5.1 系统监控

**安装 htop 和 iotop：**
```bash
sudo apt install -y htop iotop nethogs
```

**创建监控脚本：/home/deploy/scripts/system-monitor.sh**
```bash
#!/bin/bash

# 系统资源监控
echo "=== 系统监控报告 ==="
echo "时间: $(date)"
echo ""

# CPU 使用率
echo "CPU 使用率:"
top -bn1 | grep "Cpu(s)" | awk '{print $2}' | awk -F'%' '{print $1}' | awk '{print "  " $1 "%"}'

# 内存使用
echo ""
echo "内存使用:"
free -h | awk 'NR==2{printf "  总计: %s\n  已用: %s (%.2f%%)\n  可用: %s\n", $2,$3,$3*100/$2,$7}'

# 磁盘使用
echo ""
echo "磁盘使用:"
df -h / | awk 'NR==2{printf "  总计: %s\n  已用: %s (%.2f%%)\n  可用: %s\n", $2,$3,$3*100/$2,$4}'

# MySQL 状态
echo ""
echo "MySQL 状态:"
systemctl is-active mysql

# Redis 状态
echo ""
echo "Redis 状态:"
systemctl is-active redis-server

# Nginx 状态
echo ""
echo "Nginx 状态:"
systemctl is-active nginx

# PHP-FPM 状态
echo ""
echo "PHP-FPM 状态:"
systemctl is-active php8.3-fpm

# Laravel 应用状态
echo ""
echo "Laravel 应用:"
curl -s http://localhost/api/health | jq -r '"  状态: " + .status'

echo ""
echo "=== 监控完成 ==="
```

**设置定时监控：**
```bash
chmod +x /home/deploy/scripts/system-monitor.sh

# 添加到 crontab (每小时执行)
crontab -e
# 添加：
0 * * * * /home/deploy/scripts/system-monitor.sh >> /var/log/system-monitor.log 2>&1
```

### 5.2 日志管理

**配置 logrotate：/etc/logrotate.d/binaryecom20**
```
/var/www/binaryecom20/Files/core/storage/logs/*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 deploy deploy
    postrotate
        systemctl reload nginx
        systemctl reload php8.3-fpm
    endscript
}

/var/log/nginx/binaryecom20_*.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data adm
    postrotate
        systemctl reload nginx
    endscript
}

/var/log/php8.3-fpm_errors.log {
    daily
    missingok
    rotate 52
    compress
    delaycompress
    notifempty
    create 644 www-data adm
    postrotate
        systemctl reload php8.3-fpm
    endscript
}
```

### 5.3 健康检查

**创建健康检查脚本：/home/deploy/scripts/health-check.sh**
```bash
#!/bin/bash

# 检查各项服务
ERRORS=0

# 检查 Nginx
if ! systemctl is-active --quiet nginx; then
    echo "❌ Nginx 未运行"
    ERRORS=$((ERRORS + 1))
else
    echo "✅ Nginx 正常"
fi

# 检查 PHP-FPM
if ! systemctl is-active --quiet php8.3-fpm; then
    echo "❌ PHP-FPM 未运行"
    ERRORS=$((ERRORS + 1))
else
    echo "✅ PHP-FPM 正常"
fi

# 检查 MySQL
if ! systemctl is-active --quiet mysql; then
    echo "❌ MySQL 未运行"
    ERRORS=$((ERRORS + 1))
else
    echo "✅ MySQL 正常"
fi

# 检查 Redis
if ! systemctl is-active --quiet redis-server; then
    echo "❌ Redis 未运行"
    ERRORS=$((ERRORS + 1))
else
    echo "✅ Redis 正常"
fi

# 检查 Laravel 应用
HEALTH=$(curl -s http://localhost/api/health 2>/dev/null | jq -r '.status')
if [ "$HEALTH" != "ok" ]; then
    echo "❌ Laravel 应用异常"
    ERRORS=$((ERRORS + 1))
else
    echo "✅ Laravel 应用正常"
fi

# 检查磁盘空间
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 90 ]; then
    echo "❌ 磁盘空间不足: ${DISK_USAGE}%"
    ERRORS=$((ERRORS + 1))
else
    echo "✅ 磁盘空间充足: ${DISK_USAGE}%"
fi

# 检查内存使用
MEM_USAGE=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
if [ $MEM_USAGE -gt 90 ]; then
    echo "❌ 内存使用率过高: ${MEM_USAGE}%"
    ERRORS=$((ERRORS + 1))
else
    echo "✅ 内存使用正常: ${MEM_USAGE}%"
fi

# 输出结果
echo ""
if [ $ERRORS -eq 0 ]; then
    echo "✅ 所有检查通过"
    exit 0
else
    echo "❌ 发现 $ERRORS 个问题"
    exit 1
fi
```

**设置定时健康检查：**
```bash
chmod +x /home/deploy/scripts/health-check.sh

# 每5分钟检查一次
crontab -e
# 添加：
*/5 * * * * /home/deploy/scripts/health-check.sh >> /var/log/health-check.log 2>&1
```

---

## 🚀 第六阶段：性能优化

### 6.1 MySQL 优化

**检查慢查询：**
```bash
# 查看慢查询日志
sudo tail -f /var/log/mysql/mysql-slow.log

# 分析慢查询
mysqldumpslow -s c -t 10 /var/log/mysql/mysql-slow.log
```

**索引优化脚本：**
```bash
#!/bin/bash
# scripts/db-optimize.sh

echo "开始数据库优化..."

# 分析表
mysql -u binary_user -p binary_db << EOF
ANALYZE TABLE users, transactions, orders, products;
EOF

echo "数据库优化完成"
```

### 6.2 缓存预热

**创建缓存预热脚本：/home/deploy/scripts/cache-warmup.sh**
```bash
#!/bin/bash

echo "开始缓存预热..."

# 预热通用设置
php artisan tinker << EOF
Cache::rememberForever('general_settings', function() {
    return \App\Models\GeneralSetting::pluck('data', 'key')->toArray();
});
EOF

# 预热语言包
php artisan lang:publish

echo "缓存预热完成"
```

---

## 📋 第七阶段：部署验证

### 7.1 功能测试清单

**基础功能：**
```bash
# 1. 首页访问
curl -I http://yourdomain.com
# 应返回：HTTP/1.1 200 OK

# 2. 健康检查
curl -s http://yourdomain.com/api/health | jq .
# 应返回：{"status":"ok",...}

# 3. 管理后台
# 访问：http://yourdomain.com/admin
# 应显示登录页面

# 4. 数据库连接
php artisan tinker
>>> DB::connection()->getPdo();
# 应返回：PDO 对象

# 5. 缓存测试
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');
# 应返回：'value'

# 6. 队列测试
php artisan queue:work --once
# 应成功处理队列任务
```

### 7.2 性能测试

**使用 Apache Bench：**
```bash
# 测试首页
ab -n 100 -c 10 http://yourdomain.com/

# 预期结果：
# - Requests per second: > 50
# - Time per request: < 20ms
# - 失败率: < 1%
```

**使用 k6：**
```bash
# 安装 k6
curl https://github.com/grafana/k6/releases/download/v0.46.0/k6-v0.46.0-linux-amd64.tar.gz -L | tar xvz
sudo mv k6-v0.46.0-linux-amd64/k6 /usr/local/bin

# 运行压测
k6 run scripts/loadtest.js

# 预期结果：
# - http_req_duration: p(95) < 500ms
# - http_req_failed: < 1%
```

### 7.3 安全检查

**SSL 检查：**
```bash
# 使用 SSL Labs
# 访问：https://www.ssllabs.com/ssltest/
# 预期：A+ 评级

# 本地检查
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com < /dev/null 2>/dev/null | openssl x509 -noout -dates
```

**安全头检查：**
```bash
curl -I http://yourdomain.com
# 应包含：
# - Strict-Transport-Security
# - X-Content-Type-Options
# - X-Frame-Options
# - X-XSS-Protection
```

---

## 🔄 第八阶段：上线后监控

### 8.1 实时监控

**创建监控仪表板：**
```bash
# 创建简单的监控页面
sudo tee /var/www/html/monitor.html << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>BinaryEcom20 监控</title>
    <meta http-equiv="refresh" content="60">
    <style>
        body { font-family: Arial; margin: 20px; }
        .metric { padding: 10px; margin: 5px 0; border-left: 4px solid #4CAF50; }
        .error { border-left-color: #f44336; }
    </style>
</head>
<body>
    <h1>BinaryEcom20 监控仪表板</h1>
    <div class="metric">更新时间: $(date)</div>
    <div class="metric">CPU: $(top -bn1 | grep "Cpu(s)" | awk '{print $2}')</div>
    <div class="metric">内存: $(free -h | awk 'NR==2{print $3"/"$2}')</div>
    <div class="metric">磁盘: $(df -h / | awk 'NR==2{print $3"/"$2" ("$5")"}')</div>
</body>
</html>
EOF
```

### 8.2 告警设置

**创建告警脚本：/home/deploy/scripts/alert.sh**
```bash
#!/bin/bash

# 检查服务状态
if ! systemctl is-active --quiet nginx; then
    echo "警告: Nginx 服务已停止" | mail -s "服务器告警" admin@yourdomain.com
fi

# 检查磁盘空间
DISK_USAGE=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 85 ]; then
    echo "警告: 磁盘使用率已达 ${DISK_USAGE}%" | mail -s "磁盘空间告警" admin@yourdomain.com
fi

# 检查 Laravel 错误日志
ERROR_COUNT=$(tail -n 100 /var/www/binaryecom20/Files/core/storage/logs/laravel.log | grep -c "ERROR")
if [ $ERROR_COUNT -gt 10 ]; then
    echo "警告: 最近100行日志中发现 $ERROR_COUNT 个错误" | mail -s "Laravel错误告警" admin@yourdomain.com
fi
```

---

## 📅 维护计划

### 每日任务
- [ ] 检查系统资源使用
- [ ] 查看错误日志
- [ ] 检查备份状态
- [ ] 监控 SSL 证书到期

### 每周任务
- [ ] 更新系统补丁
- [ ] 检查慢查询
- [ ] 分析访问日志
- [ ] 测试备份恢复

### 每月任务
- [ ] 清理旧日志
- [ ] 优化数据库
- [ ] 安全审计
- [ ] 性能评估

---

## 🚨 紧急回滚方案

### 快速回滚命令

```bash
#!/bin/bash
# emergency-rollback.sh

echo "开始紧急回滚..."

# 1. 停止 Nginx
sudo systemctl stop nginx

# 2. 回滚代码
cd /var/www/binaryecom20
sudo git reset --hard HEAD~1
sudo chown -R deploy:deploy .

# 3. 清理缓存
sudo -u deploy php artisan cache:clear
sudo -u deploy php artisan config:clear
sudo -u deploy php artisan route:clear

# 4. 恢复数据库 (如果有备份)
# mysql -u root -p binary_db < /backup/latest.sql

# 5. 重启服务
sudo systemctl start nginx
sudo systemctl restart php8.3-fpm

# 6. 验证
sleep 5
curl -I http://yourdomain.com

echo "回滚完成"
```

### 灾难恢复

**完整备份脚本：/home/deploy/scripts/backup.sh**
```bash
#!/bin/bash

BACKUP_DIR="/backup/$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR

# 1. 备份代码
tar -czf $BACKUP_DIR/code.tar.gz /var/www/binaryecom20

# 2. 备份数据库
mysqldump -u binary_user -p binary_db | gzip > $BACKUP_DIR/database.sql.gz

# 3. 备份配置
cp /etc/nginx/sites-available/binaryecom20 $BACKUP_DIR/nginx.conf
cp /etc/php/8.3/fpm/pool.d/www.conf $BACKUP_DIR/php-fpm.conf

# 4. 备份 SSL 证书
cp -r /etc/letsencrypt $BACKUP_DIR/ssl

# 5. 压缩备份
tar -czf /backup/binaryecom20_backup_$(date +%Y%m%d_%H%M%S).tar.gz $BACKUP_DIR

# 6. 清理临时文件
rm -rf $BACKUP_DIR

echo "备份完成: /backup/binaryecom20_backup_$(date +%Y%m%d_%H%M%S).tar.gz"
```

**设置自动备份：**
```bash
chmod +x /home/deploy/scripts/backup.sh

# 每天凌晨2点备份
crontab -e
# 添加：
0 2 * * * /home/deploy/scripts/backup.sh >> /var/log/backup.log 2>&1

# 保留最近30天的备份
find /backup -name "binaryecom20_backup_*.tar.gz" -mtime +30 -delete
```

---

## ✅ 部署完成检查清单

### 上线前 (Pre-Deployment)
- [ ] 代码审查完成
- [ ] 所有测试通过
- [ ] 数据库迁移准备就绪
- [ ] .env 配置检查无误
- [ ] SSL 证书已获取
- [ ] 备份策略已制定
- [ ] 回滚方案已测试

### 上线中 (During Deployment)
- [ ] 维护模式已开启
- [ ] 数据库已迁移
- [ ] 缓存已清理
- [ ] 权限已设置
- [ ] 服务已重启
- [ ] 监控已启用

### 上线后 (Post-Deployment)
- [ ] 健康检查通过
- [ ] 功能测试通过
- [ ] 性能测试达标
- [ ] 日志无错误
- [ ] 监控数据正常
- [ ] 用户反馈良好
- [ ] 维护模式已关闭

---

## 📞 紧急联系

**技术支持：**
- 运维团队: +1-XXX-XXX-XXXX
- 邮箱: ops@yourdomain.com
- Slack: #emergency-ops

**第三方服务：**
- 云服务商支持: https://cloud.provider.com/support
- 域名注册商: https://registrar.com/support
- CDN 服务: https://cdn.provider.com/support

---

## 📚 附录

### 常用命令速查

```bash
# 查看应用日志
tail -f /var/www/binaryecom20/Files/core/storage/logs/laravel.log

# 查看 Nginx 日志
tail -f /var/log/nginx/binaryecom20_access.log
tail -f /var/log/nginx/binaryecom20_error.log

# 查看 PHP-FPM 日志
tail -f /var/log/php8.3-fpm_errors.log

# 重启所有服务
sudo systemctl restart nginx php8.3-fpm mysql redis-server

# 清理 Laravel 缓存
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 数据库维护
php artisan migrate:status
php artisan db:show
php artisan db:table users

# 队列管理
php artisan queue:work --verbose
php artisan queue:restart
php artisan queue:monitor
```

### 文件路径速查

```
/var/www/binaryecom20/Files/core              # 应用根目录
/var/www/binaryecom20/Files/core/public       # Web 根目录
/var/www/binaryecom20/Files/core/.env         # 环境配置
/var/www/binaryecom20/Files/core/storage      # 存储目录
/var/www/binaryecom20/Files/core/bootstrap/cache  # 缓存目录

/etc/nginx/sites-available/binaryecom20       # Nginx 配置
/etc/php/8.3/fpm/pool.d/www.conf              # PHP-FPM 配置
/etc/mysql/mysql.conf.d/mysqld.cnf            # MySQL 配置
/etc/redis/redis.conf                          # Redis 配置

/var/log/nginx/                               # Nginx 日志
/var/log/mysql/                               # MySQL 日志
/var/log/php8.3-fpm_errors.log               # PHP-FPM 日志
/backup/                                      # 备份目录
```

---

**文档版本：** v1.0
**最后更新：** 2025-12-21
**下次审查：** 2025-12-28
**负责人：** DevOps 团队

---

**祝部署顺利！ 🚀**
