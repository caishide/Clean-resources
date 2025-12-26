# IPv6 云服务器 Git 自动同步解决方案

> 本文档提供完整的 IPv6 云服务器与 GitHub 自动同步方案，解决网络连接问题并建立长期稳定的开发流程。

---

## 目录

- [一、问题分析](#一问题分析)
- [二、解决方案概览](#二解决方案概览)
- [三、方案一：Webhook 自动部署（推荐）](#三方案一webhook-自动部署推荐)
- [四、方案二：定时任务同步](#四方案二定时任务同步)
- [五、方案三：镜像仓库同步](#五方案三镜像仓库同步)
- [六、方案四：SSH 隧道转发](#六方案四ssh-隧道转发)

---

## 一、问题分析

### 1.1 当前问题

- 云服务器使用 IPv6 网络
- 无法直接连接到 GitHub（IPv4）
- `git pull` 命令失败：`Couldn't connect to server`

### 1.2 根本原因

GitHub 主要支持 IPv4，部分 IPv6 网络可能无法直接访问。

---

## 二、解决方案概览

| 方案 | 难度 | 稳定性 | 适用场景 |
|------|------|--------|----------|
| Webhook 自动部署 | ⭐⭐ | ⭐⭐⭐⭐⭐ | 推荐使用，实时同步 |
| 定时任务同步 | ⭐ | ⭐⭐⭐ | 简单场景，定期同步 |
| 镜像仓库同步 | ⭐⭐⭐ | ⭐⭐⭐⭐ | 大型项目，国内加速 |
| SSH 隧道转发 | ⭐⭐⭐⭐ | ⭐⭐⭐ | 复杂网络环境 |

---

## 三、方案一：Webhook 自动部署（推荐）

### 3.1 工作原理

```
本地服务器 → GitHub → Webhook → 云服务器 → 自动拉取
```

### 3.2 实施步骤

#### 步骤 1：在云服务器创建 Webhook 接收脚本

```bash
# 在云服务器执行
cd /www/wwwroot/h2-home.cn

# 创建 webhook 目录
mkdir -p webhooks
cd webhooks
```

创建 `deploy.php` 文件：

```php
<?php
// /www/wwwroot/h2-home.cn/webhooks/deploy.php

// 配置
$secret = 'your_webhook_secret_key';  // 自定义密钥
$projectPath = '/www/wwwroot/h2-home.cn';
$logFile = '/www/wwwroot/h2-home.cn/webhooks/deploy.log';

// 获取请求数据
$payload = file_get_contents('php://input');
$headers = getallheaders();
$signature = isset($headers['X-Hub-Signature-256']) ? $headers['X-Hub-Signature-256'] : '';

// 验证签名
if (!empty($secret)) {
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    if (!hash_equals($expectedSignature, $signature)) {
        http_response_code(403);
        echo 'Invalid signature';
        logMessage('Invalid signature from IP: ' . $_SERVER['REMOTE_ADDR']);
        exit;
    }
}

// 解析 JSON
$data = json_decode($payload, true);
$ref = isset($data['ref']) ? $data['ref'] : '';

// 只处理 master 分支的推送
if ($ref === 'refs/heads/master') {
    logMessage('Deploy triggered for master branch');
    
    // 执行部署
    $output = shell_exec("cd {$projectPath} && git pull origin master 2>&1");
    
    logMessage('Git pull output: ' . $output);
    
    // 可选：执行其他命令
    // shell_exec("cd {$projectPath} && php artisan migrate --force");
    // shell_exec("cd {$projectPath} && php artisan cache:clear");
    
    echo 'Deployment successful';
} else {
    echo 'Not a master branch push';
}

function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}
?>
```

#### 步骤 2：设置文件权限

```bash
# 设置 webhook 目录权限
chmod -R 755 /www/wwwroot/h2-home.cn/webhooks

# 设置日志文件可写
touch /www/wwwroot/h2-home.cn/webhooks/deploy.log
chmod 666 /www/wwwroot/h2-home.cn/webhooks/deploy.log
```

#### 步骤 3：配置 Nginx（如果使用）

在宝塔面板中：

1. 进入 **网站** → 找到你的网站 → **设置**
2. 点击 **配置文件**
3. 添加 location 块：

```nginx
location /webhooks/deploy.php {
    fastcgi_pass unix:/tmp/php-cgi.sock;  # 根据实际 PHP 配置调整
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

#### 步骤 4：在 GitHub 配置 Webhook

1. 打开 GitHub 仓库页面
2. 点击 **Settings** → **Webhooks** → **Add webhook**
3. 配置：
   - **Payload URL**: `https://你的域名/webhooks/deploy.php`
   - **Content type**: `application/json`
   - **Secret**: 输入你设置的密钥
   - **Events**: 选择 "Just the push event"
4. 点击 **Add webhook**

#### 步骤 5：测试 Webhook

```bash
# 在本地服务器推送代码
cd /www/wwwroot/binaryecom20
echo "test" >> test.txt
git add test.txt
git commit -m "test webhook"
git push origin master
```

检查云服务器日志：

```bash
tail -f /www/wwwroot/h2-home.cn/webhooks/deploy.log
```

### 3.3 优点

- ✅ 实时同步，推送后立即部署
- ✅ 不需要云服务器主动连接 GitHub
- ✅ 可以添加部署前后的钩子脚本
- ✅ 支持多环境部署

---

## 四、方案二：定时任务同步

### 4.1 工作原理

使用宝塔面板的定时任务，定期执行 `git pull`。

### 4.2 实施步骤

#### 步骤 1：创建同步脚本

```bash
# 在云服务器创建同步脚本
cat > /www/wwwroot/h2-home.cn/sync.sh << 'EOF'
#!/bin/bash
PROJECT_DIR="/www/wwwroot/h2-home.cn"
LOG_FILE="/www/wwwroot/h2-home.cn/sync.log"

cd $PROJECT_DIR

# 记录开始时间
echo "[$(date '+%Y-%m-%d %H:%M:%S')] 开始同步..." >> $LOG_FILE

# 拉取代码
OUTPUT=$(git pull origin master 2>&1)
echo "$OUTPUT" >> $LOG_FILE

# 检查是否有更新
if echo "$OUTPUT" | grep -q "Already up to date"; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 无需更新" >> $LOG_FILE
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] 代码已更新" >> $LOG_FILE
    
    # 可选：执行其他命令
    # php artisan migrate --force
    # php artisan cache:clear
fi

echo "----------------------------------------" >> $LOG_FILE
EOF

# 设置执行权限
chmod +x /www/wwwroot/h2-home.cn/sync.sh
```

#### 步骤 2：配置宝塔定时任务

1. 登录宝塔面板
2. 点击 **计划任务**
3. 添加任务：
   - **任务类型**: Shell 脚本
   - **任务名称**: Git 代码同步
   - **执行周期**: 每 10 分钟
   - **脚本内容**: `/www/wwwroot/h2-home.cn/sync.sh`

#### 步骤 3：手动测试

```bash
# 手动执行同步脚本
/www/wwwroot/h2-home.cn/sync.sh

# 查看日志
cat /www/wwwroot/h2-home.cn/sync.log
```

### 4.3 优点

- ✅ 配置简单
- ✅ 不需要 GitHub Webhook
- ✅ 可以设置同步频率

### 4.4 缺点

- ❌ 不是实时同步
- ❌ 仍然需要解决 GitHub 连接问题

---

## 五、方案三：镜像仓库同步

### 5.1 工作原理

使用国内 Git 托管平台（Gitee）作为镜像仓库。

```
本地服务器 → GitHub → Gitee → 云服务器
```

### 5.2 实施步骤

#### 步骤 1：在 Gitee 创建镜像仓库

1. 登录 Gitee (https://gitee.com)
2. 点击 **+** → **从 GitHub / GitLab 导入仓库**
3. 输入 GitHub 仓库地址：`https://github.com/caishide/Clean-resources.git`
4. 点击 **导入**

#### 步骤 2：配置 Gitee 同步

在 Gitee 仓库设置中：

1. 点击 **管理** → **仓库镜像管理**
2. 添加 GitHub 仓库作为镜像源
3. 设置自动同步（每 1 小时或手动触发）

#### 步骤 3：云服务器使用 Gitee

```bash
# 在云服务器修改远程仓库地址
cd /www/wwwroot/h2-home.cn
git remote set-url origin https://gitee.com/你的用户名/Clean-resources.git

# 拉取代码
git pull origin master
```

#### 步骤 4：配置双远程仓库

```bash
# 添加 Gitee 作为额外远程仓库
git remote add gitee https://gitee.com/你的用户名/Clean-resources.git

# 推送到 GitHub
git push origin master

# 从 Gitee 拉取
git pull gitee master
```

### 5.3 优点

- ✅ 国内访问速度快
- ✅ 支持自动镜像同步
- ✅ 免费

---

## 六、方案四：SSH 隧道转发

### 6.1 工作原理

通过本地服务器建立 SSH 隧道，转发 GitHub 流量到云服务器。

```
云服务器 → SSH 隧道 → 本地服务器 → GitHub
```

### 6.2 实施步骤

#### 步骤 1：在本地服务器建立 SSH 隧道

```bash
# 在本地服务器执行
ssh -N -R 9418:github.com:22 root@[你的IPv6地址]

# 或者使用 autossh 保持连接
autossh -M 0 -N -R 9418:github.com:22 root@[你的IPv6地址]
```

#### 步骤 2：配置云服务器 Git

```bash
# 在云服务器配置 Git 使用隧道
git config --global core.sshCommand "ssh -p 9418"

# 修改远程仓库地址为 SSH
git remote set-url origin git@github.com:caishide/Clean-resources.git

# 拉取代码
git pull origin master
```

### 6.3 优点

- ✅ 可以直接使用 GitHub
- ✅ 安全性高

### 6.4 缺点

- ❌ 需要本地服务器一直在线
- ❌ 配置复杂

---

## 七、推荐方案组合

### 7.1 最佳实践

**Webhook + 镜像仓库**

1. 主仓库：GitHub（用于备份和开源）
2. 镜像仓库：Gitee（用于云服务器同步）
3. 自动部署：Webhook（实时触发）

### 7.2 实施流程

```bash
# 1. 本地开发
git add .
git commit -m "update"
git push origin master    # 推送到 GitHub
git push gitee master     # 推送到 Gitee

# 2. 云服务器配置
cd /www/wwwroot/h2-home.cn
git remote add gitee https://gitee.com/你的用户名/Clean-resources.git

# 3. 配置 Webhook 监听 Gitee
# 在 Gitee 配置 Webhook，指向云服务器的 deploy.php
```

---

## 八、故障排查

### 8.1 Webhook 不触发

```bash
# 检查日志
tail -f /www/wwwroot/h2-home.cn/webhooks/deploy.log

# 检查 Nginx 配置
nginx -t

# 重启 Nginx
systemctl restart nginx
```

### 8.2 Git 拉取失败

```bash
# 检查网络连接
ping github.com
ping gitee.com

# 检查 Git 配置
git config --list

# 查看详细错误
GIT_CURL_VERBOSE=1 git pull origin master
```

### 8.3 权限问题

```bash
# 修复文件权限
chown -R www:www /www/wwwroot/h2-home.cn
chmod -R 755 /www/wwwroot/h2-home.cn
```

---

## 九、快速开始

### 最简单方案（5 分钟配置）

```bash
# 1. 在云服务器创建同步脚本
cat > /www/wwwroot/h2-home.cn/sync.sh << 'EOF'
#!/bin/bash
cd /www/wwwroot/h2-home.cn
git pull origin master
EOF
chmod +x /www/wwwroot/h2-home.cn/sync.sh

# 2. 在宝塔面板添加定时任务
# 任务类型: Shell 脚本
# 执行周期: 每 10 分钟
# 脚本内容: /www/wwwroot/h2-home.cn/sync.sh

# 3. 测试
/www/wwwroot/h2-home.cn/sync.sh
```

---

**文档版本**: v1.0  
**最后更新**: 2025-12-26
