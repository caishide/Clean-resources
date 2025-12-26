# 云服务器代码同步解决方案

## 问题分析

经过测试发现以下情况：

1. **本地服务器网络环境**
   - 只有 IPv4 地址（192.168.1.17）
   - 不支持 IPv6 连接

2. **云服务器网络环境**
   - IPv6 地址：240e:95d:c01:700::4:2a9c
   - IPv4 地址：47.52.61.90（域名 h2-home.cn）
   - SSH 端口 22 无法从本地访问（可能被阿里云安全组阻止）

3. **核心问题**
   - 本地无法通过 SSH 直接连接到云服务器
   - 云服务器无法访问 GitHub（IPv6 网络限制）

---

## 解决方案对比

| 方案 | 难度 | 可靠性 | 自动化程度 | 推荐度 |
|------|------|--------|------------|--------|
| 方案1: 开放安全组 SSH 端口 | ⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| 方案2: Git Webhook 自动部署 | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ |
| 方案3: 宝塔面板手动上传 | ⭐ | ⭐⭐⭐ | ⭐ | ⭐⭐ |
| 方案4: 中转服务器同步 | ⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ |

---

## 方案1: 开放阿里云安全组 SSH 端口（推荐）

### 步骤

1. **登录阿里云控制台**
   - 访问：https://ecs.console.aliyun.com/

2. **找到安全组设置**
   - 进入：云服务器 ECS → 实例
   - 找到您的服务器实例（IP: 47.52.61.90）
   - 点击：更多 → 网络和安全组 → 安全组配置

3. **添加入站规则**
   - 方向：入站
   - 协议类型：自定义 TCP
   - 端口范围：22/22
   - 授权对象：0.0.0.0/0（或限制为您的本地 IP）
   - 优先级：1

4. **测试连接**
   ```bash
   ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 root@47.52.61.90 "echo '连接成功'"
   ```

5. **使用同步脚本**
   ```bash
   /www/wwwroot/binaryecom20/Files/core/sync-to-cloud.sh -y
   ```

### 优点
- ✅ 最简单直接
- ✅ 完全自动化
- ✅ 可靠性高

### 缺点
- ⚠️ 需要开放 SSH 端口（安全风险）

---

## 方案2: Git Webhook 自动部署

### 架构

```
本地开发 → GitHub → Webhook → 云服务器自动拉取
```

### 步骤

#### 2.1 在云服务器上配置 Git

```bash
# 登录云服务器（通过宝塔面板终端）
cd /www/wwwroot/h2-home.cn

# 初始化 Git 仓库
git init
git remote add origin https://github.com/caishide/Clean-resources.git

# 配置 Git 凭证（使用 Personal Access Token）
git config --global credential.helper store
```

#### 2.2 创建 Webhook 处理脚本

在云服务器上创建 `/www/wwwroot/h2-home.cn/webhook.php`：

```php
<?php
// Webhook 密钥（验证请求）
$secret = 'your-webhook-secret';

// 获取 GitHub 发送的数据
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';

// 验证签名
$expectedSignature = 'sha1=' . hash_hmac('sha1', $payload, $secret);
if (!hash_equals($expectedSignature, $signature)) {
    http_response_code(403);
    exit('Invalid signature');
}

// 解析数据
$data = json_decode($payload, true);
$ref = $data['ref'] ?? '';

// 只处理 master 分支的推送
if ($ref === 'refs/heads/master') {
    // 执行拉取命令
    $output = shell_exec('cd /www/wwwroot/h2-home.cn && git pull origin master 2>&1');
    
    // 记录日志
    file_put_contents('/www/wwwroot/h2-home.cn/webhook.log', date('Y-m-d H:i:s') . " - " . $output . "\n", FILE_APPEND);
    
    echo "Deployment successful!";
} else {
    echo "Ignoring non-master branch";
}
```

#### 2.3 配置 GitHub Webhook

1. 访问 GitHub 仓库设置
2. Settings → Webhooks → Add webhook
3. 配置：
   - Payload URL: `http://47.52.61.90/webhook.php`
   - Content type: `application/json`
   - Secret: 设置一个密钥
   - Events: 选择 "Just the push event"

#### 2.4 日常使用

```bash
# 本地开发
cd /www/wwwroot/binaryecom20
# ... 修改代码 ...

# 提交并推送
git add .
git commit -m "update"
git push origin master

# 云服务器会自动拉取最新代码
```

### 优点
- ✅ 完全自动化
- ✅ 不需要开放 SSH 端口
- ✅ 有完整的版本历史

### 缺点
- ⚠️ 配置较复杂
- ⚠️ 云服务器需要能访问 GitHub（可能需要配置代理）

---

## 方案3: 宝塔面板手动上传

### 步骤

1. **创建文件包**
   ```bash
   cd /www/wwwroot/binaryecom20
   tar -czf /tmp/sync-files.tar.gz \
       Files/core/app/ \
       Files/core/config/ \
       Files/core/database/ \
       Files/core/resources/ \
       Files/core/routes/
   ```

2. **通过宝塔面板上传**
   - 访问：http://47.52.61.90:13040/bfe8cfc3
   - 进入：文件 → /www/wwwroot/h2-home.cn
   - 上传：/tmp/sync-files.tar.gz
   - 解压：右键 → 解压

3. **执行数据库迁移**
   ```bash
   cd /www/wwwroot/h2-home.cn/Files/core
   php artisan migrate --force
   ```

### 优点
- ✅ 简单直接
- ✅ 不需要额外配置

### 缺点
- ❌ 手动操作，容易出错
- ❌ 效率低

---

## 方案4: 使用中转服务器

### 架构

```
本地 → 中转服务器（有 IPv6）→ 云服务器
```

### 步骤

1. **准备中转服务器**
   - 需要一台同时支持 IPv4 和 IPv6 的服务器
   - 可以使用便宜的 VPS（如搬瓦工、Vultr）

2. **配置 SSH 隧道**
   ```bash
   # 在中转服务器上
   ssh -N -R 2222:localhost:22 root@240e:95d:c01:700::4:2a9c
   ```

3. **本地通过中转连接**
   ```bash
   ssh -p 2222 root@中转服务器IP
   ```

### 优点
- ✅ 不需要开放云服务器 SSH 端口
- ✅ 相对安全

### 缺点
- ❌ 需要额外的服务器成本
- ❌ 配置复杂

---

## 推荐方案

### 短期方案（立即可用）

**使用方案3：宝塔面板手动上传**

配合脚本 [`sync-to-cloud-api.sh`](Files/core/sync-to-cloud-api.sh) 自动打包：

```bash
# 1. 创建文件包
/www/wwwroot/binaryecom20/Files/core/sync-to-cloud-api.sh -d

# 2. 通过宝塔面板上传生成的压缩包
# 3. 在云服务器上解压
```

### 长期方案（推荐）

**使用方案1：开放阿里云安全组 SSH 端口**

这是最简单、最可靠的方案，只需要一次性配置即可。

---

## 快速参考

### 本地项目路径
```
/www/wwwroot/binaryecom20
```

### 云服务器信息
```
IPv4: 47.52.61.90
IPv6: 240e:95d:c01:700::4:2a9c
域名: h2-home.cn
宝塔面板: http://47.52.61.90:13040/bfe8cfc3
项目路径: /www/wwwroot/h2-home.cn
```

### 相关脚本
- SSH 同步脚本：[`Files/core/sync-to-cloud.sh`](Files/core/sync-to-cloud.sh)
- API 同步脚本：[`Files/core/sync-to-cloud-api.sh`](Files/core/sync-to-cloud-api.sh)
