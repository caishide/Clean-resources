# 云服务器代码同步解决方案 - IPv4/IPv6 跨网络

## 问题分析

### 网络环境

| 设备 | IPv4 | IPv6 | SSH 端口 |
|------|------|------|----------|
| **本地开发服务器** | ✅ 192.168.1.17 | ❌ 无 | - |
| **云服务器** | ❌ 无 | ✅ 240e:95d:c01:700::4:2a9c | 22 (仅 IPv6) |
| **您的个人电脑** | ✅ 有 | ✅ 有 | - |

### 核心问题

1. **本地开发服务器**（192.168.1.17）**没有 IPv6**，无法直接连接云服务器的 IPv6 SSH
2. **云服务器** **只有 IPv6**，没有 IPv4 SSH
3. 两者处于不同的网络协议栈，**无法直接建立 SSH 连接**

---

## 解决方案

### 方案 1: 使用您的个人电脑作为中转（推荐）

如果您个人电脑同时有 IPv4 和 IPv6，可以作为中转服务器。

#### 架构

```
本地开发服务器 (IPv4) → 个人电脑 (IPv4+IPv6) → 云服务器 (IPv6)
```

#### 步骤

**1. 在个人电脑上设置 SSH 隧道**

```bash
# 在您的个人电脑上执行
ssh -N -R 2222:localhost:22 root@240e:95d:c01:700::4:2a9c

# 这会在云服务器上创建一个反向隧道，将云服务器的 2222 端口映射到您个人电脑的 22 端口
```

**2. 在本地开发服务器上通过个人电脑连接**

```bash
# 在本地开发服务器上
ssh -o ProxyCommand="ssh -W %h:%p 您的个人电脑用户名@您个人电脑IP" root@240e:95d:c01:700::4:2a9c
```

**3. 使用 rsync 同步**

```bash
# 在本地开发服务器上
rsync -avz -e "ssh -o ProxyCommand='ssh -W %h:%p 用户@个人电脑IP'" \
  /www/wwwroot/binaryecom20/ \
  root@240e:95d:c01:700::4:2a9c:/www/wwwroot/h2-home.cn/
```

---

### 方案 2: Git Webhook 自动部署（最推荐）

由于云服务器无法访问 GitHub，我们需要使用反向方式。

#### 架构

```
本地开发 → GitHub → 定时任务拉取 → 云服务器
```

#### 步骤

**1. 在云服务器上配置 Git（通过宝塔面板终端）**

```bash
# 登录云服务器（通过宝塔面板）
cd /www/wwwroot/h2-home.cn

# 初始化 Git 仓库
git init
git remote add origin https://github.com/caishide/Clean-resources.git

# 配置 Git（如果需要）
git config --global user.name "Your Name"
git config --global user.email "your@email.com"
```

**2. 创建自动拉取脚本**

在云服务器上创建 `/www/wwwroot/h2-home.cn/auto-pull.sh`：

```bash
#!/bin/bash
cd /www/wwwroot/h2-home.cn
echo "$(date '+%Y-%m-%d %H:%M:%S') - 开始自动拉取..." >> /www/wwwroot/h2-home.cn/auto-pull.log

# 拉取最新代码
git fetch origin
git reset --hard origin/master

# 如果有数据库迁移，执行迁移
cd Files/core && php artisan migrate --force

echo "$(date '+%Y-%m-%d %H:%M:%S') - 拉取完成" >> /www/wwwroot/h2-home.cn/auto-pull.log
```

**3. 设置定时任务**

在宝塔面板中：
1. 进入：计划任务
2. 添加任务：
   - 任务类型：Shell 脚本
   - 执行周期：每 5 分钟
   - 脚本内容：`/www/wwwroot/h2-home.cn/auto-pull.sh`

**4. 日常使用**

```bash
# 在本地开发服务器上
cd /www/wwwroot/binaryecom20
git add .
git commit -m "update"
git push origin master

# 等待最多 5 分钟，云服务器会自动拉取
```

---

### 方案 3: 宝塔面板手动上传（简单但繁琐）

#### 步骤

**1. 在本地开发服务器上打包**

```bash
cd /www/wwwroot/binaryecom20

# 只打包必要的文件
tar -czf /tmp/sync-files.tar.gz \
  Files/core/app/ \
  Files/core/config/ \
  Files/core/database/ \
  Files/core/resources/ \
  Files/core/routes/ \
  Files/core/composer.json \
  Files/core/composer.lock
```

**2. 通过宝塔面板上传**

1. 访问宝塔面板：http://[240e:95d:c01:700::4:2a9c]:13040/bfe8cfc3
2. 进入：文件 → /www/wwwroot/h2-home.cn
3. 上传：/tmp/sync-files.tar.gz
4. 解压：右键点击文件 → 解压

**3. 执行数据库迁移（如果需要）**

在宝塔面板终端中：
```bash
cd /www/wwwroot/h2-home.cn/Files/core
php artisan migrate --force
```

---

### 方案 4: 使用 GitHub Actions + Webhook

#### 架构

```
本地开发 → GitHub → GitHub Actions → 通知云服务器 → 云服务器拉取
```

#### 步骤

**1. 在云服务器上创建 Webhook 接收脚本**

创建 `/www/wwwroot/h2-home.cn/deploy.php`：

```php
<?php
// Webhook 密钥
$secret = 'your-secret-key';

// 验证请求
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

if (!$signature) {
    http_response_code(403);
    exit('No signature');
}

$expected = 'sha1=' . hash_hmac('sha1', $payload, $secret);
if (!hash_equals($expected, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// 执行部署
$data = json_decode($payload, true);
if (($data['ref'] ?? '') === 'refs/heads/master') {
    $output = shell_exec('cd /www/wwwroot/h2-home.cn && git pull origin master 2>&1');
    file_put_contents('/www/wwwroot/h2-home.cn/deploy.log', date('Y-m-d H:i:s') . "\n" . $output . "\n", FILE_APPEND);
    echo "Deployed!";
} else {
    echo "Ignored";
}
```

**2. 创建 GitHub Actions 工作流**

在本地创建 `.github/workflows/deploy.yml`：

```yaml
name: Deploy to Cloud Server

on:
  push:
    branches: [ master ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - name: Send webhook
        run: |
          curl -X POST \
            -H "Content-Type: application/json" \
            -H "X-Hub-Signature: ${{ secrets.WEBHOOK_SECRET }}" \
            -d '{"ref": "refs/heads/master"}' \
            http://47.52.61.90/deploy.php
```

**注意**：由于云服务器只有 IPv6，GitHub Actions 可能无法直接访问。需要使用 IPv6隧道服务。

---

## 推荐方案对比

| 方案 | 难度 | 自动化 | 可靠性 | 推荐度 |
|------|------|--------|--------|--------|
| 方案1: 个人电脑中转 | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ |
| 方案2: 定时任务拉取 | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| 方案3: 手动上传 | ⭐ | ⭐ | ⭐⭐⭐ | ⭐⭐ |
| 方案4: GitHub Actions | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐ |

---

## 最佳实践：方案 2（定时任务拉取）

这是最简单、最可靠的方案。

### 完整步骤

#### 1. 在云服务器上初始化 Git

通过宝塔面板终端执行：

```bash
cd /www/wwwroot/h2-home.cn
git init
git remote add origin https://github.com/caishide/Clean-resources.git
git pull origin master
```

#### 2. 创建自动拉取脚本

```bash
cat > /www/wwwroot/h2-home.cn/auto-pull.sh << 'EOF'
#!/bin/bash
cd /www/wwwroot/h2-home.cn
LOG_FILE="/www/wwwroot/h2-home.cn/auto-pull.log"

echo "========================================" >> $LOG_FILE
echo "$(date '+%Y-%m-%d %H:%M:%S') - 开始自动拉取" >> $LOG_FILE

# 拉取最新代码
git fetch origin master
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/master)

if [ $LOCAL != $REMOTE ]; then
    echo "检测到新代码，开始更新..." >> $LOG_FILE
    git pull origin master >> $LOG_FILE 2>&1
    
    # 执行数据库迁移
    cd Files/core
    php artisan migrate --force >> $LOG_FILE 2>&1
    
    echo "更新完成" >> $LOG_FILE
else
    echo "已是最新代码" >> $LOG_FILE
fi

echo "$(date '+%Y-%m-%d %H:%M:%S') - 检查完成" >> $LOG_FILE
echo "========================================" >> $LOG_FILE
EOF

chmod +x /www/wwwroot/h2-home.cn/auto-pull.sh
```

#### 3. 在宝塔面板添加定时任务

1. 登录宝塔面板
2. 进入：计划任务
3. 添加任务：
   - 任务类型：Shell 脚本
   - 任务名称：自动拉取代码
   - 执行周期：N 分钟（建议 5-10 分钟）
   - 脚本内容：`/www/wwwroot/h2-home.cn/auto-pull.sh`

#### 4. 日常使用

```bash
# 在本地开发服务器
cd /www/wwwroot/binaryecom20

# 修改代码后
git add .
git commit -m "update: 描述您的修改"
git push origin master

# 等待 5-10 分钟，云服务器会自动更新
```

#### 5. 查看日志

```bash
# 在云服务器上查看同步日志
cat /www/wwwroot/h2-home.cn/auto-pull.log
```

---

## 快速参考

### 服务器信息

```
本地开发服务器: 192.168.1.17 (IPv4 only)
云服务器: 240e:95d:c01:700::4:2a9c (IPv6 only)
宝塔面板: http://[240e:95d:c01:700::4:2a9c]:13040/bfe8cfc3
GitHub: https://github.com/caishide/Clean-resources.git
```

### 相关文件

- 本地项目路径：`/www/wwwroot/binaryecom20`
- 云服务器路径：`/www/wwwroot/h2-home.cn`
- 同步日志：`/www/wwwroot/h2-home.cn/auto-pull.log`
