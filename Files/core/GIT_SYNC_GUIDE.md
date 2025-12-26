# Git 代码同步指南

> 本指南说明如何将本地代码推送到 GitHub，并在云服务器（IPv6）上同步更新。

---

## 目录

- [一、本地推送到 GitHub](#一本地推送到-github)
- [二、云服务器拉取更新](#二云服务器拉取更新)
- [三、IPv6 连接问题解决](#三ipv6-连接问题解决)
- [四、常见问题](#四常见问题)

---

## 一、本地推送到 GitHub

### 1.1 检查当前 Git 状态

```bash
# 进入项目目录
cd /www/wwwroot/binaryecom20

# 查看当前状态
git status

# 查看远程仓库
git remote -v
```

### 1.2 添加修改的文件

```bash
# 添加所有修改的文件
git add .

# 或者只添加特定文件
git add Files/core/DEPLOYMENT_GUIDE.md
git add Files/core/DATABASE_MIGRATION_REVIEW.md
```

### 1.3 提交更改

```bash
# 提交更改
git commit -m "docs: 优化数据库部署指南，增加安全检查脚本

- 新增部署前环境检查脚本
- 新增强制备份脚本
- 新增大表操作评估脚本
- 新增健康检查脚本
- 新增一键部署脚本
- 添加详细的执行位置说明"
```

### 1.4 推送到 GitHub

```bash
# 推送到主分支
git push origin main

# 或者推送到 master 分支
git push origin master
```

---

## 二、云服务器拉取更新

### 2.1 通过宝塔面板操作

**方式一：使用宝塔面板的 Git 功能**

1. 登录宝塔面板
2. 进入 **网站** → 找到你的网站
3. 点击 **Git** → **拉取**
4. 确认拉取最新代码

**方式二：使用宝塔终端**

```bash
# 1. 打开宝塔终端

# 2. 进入网站目录
cd /www/wwwroot/binaryecom20

# 3. 查看当前状态
git status

# 4. 拉取最新代码
git pull origin main

# 或者
git pull origin master
```

### 2.2 通过 SSH 连接云服务器（IPv6）

```bash
# 1. 在本地终端通过 IPv6 连接云服务器
ssh -6 root@[你的IPv6地址]

# 2. 输入密码登录

# 3. 进入网站目录
cd /www/wwwroot/binaryecom20

# 4. 拉取最新代码
git pull origin main
```

---

## 三、IPv6 连接问题解决

### 3.1 宝塔面板无法访问 IPv6 服务器

**问题**: 宝塔面板默认使用 IPv4，可能无法直接访问 IPv6 服务器

**解决方案**:

**方案一：使用 SSH 客户端连接**

```bash
# 使用 IPv6 地址连接
ssh -6 root@[2001:db8::1]  # 替换为你的 IPv6 地址

# 或者指定端口
ssh -6 -p 22 root@[2001:db8::1]
```

**方案二：配置 IPv6 隧道或代理**

如果本地网络不支持 IPv6，需要：
1. 使用支持 IPv6 的 VPN
2. 配置 IPv6 隧道（如 Hurricane Electric）
3. 使用云服务商提供的 IPv4 到 IPv6 的网关

### 3.2 Git 拉取失败

**问题**: `git pull` 时出现连接错误

**解决方案**:

```bash
# 1. 检查网络连接
ping6 github.com

# 2. 检查 Git 配置
git config --list

# 3. 如果使用 SSH 方式，检查 SSH 密钥
ssh -T git@github.com

# 4. 如果使用 HTTPS 方式，检查凭证
git config --global credential.helper store
```

### 3.3 使用 HTTPS 方式（推荐）

如果 SSH 连接有问题，可以改用 HTTPS 方式：

```bash
# 1. 查看当前远程仓库地址
git remote -v

# 2. 修改为 HTTPS 地址
git remote set-url origin https://github.com/你的用户名/binaryecom20.git

# 3. 拉取代码（需要输入 GitHub 用户名和密码）
git pull origin main
```

---

## 四、常见问题

### 4.1 推送时提示权限不足

```bash
# 错误信息: Permission denied (publickey)

# 解决方案：配置 SSH 密钥
# 1. 生成 SSH 密钥
ssh-keygen -t ed25519 -C "your_email@example.com"

# 2. 查看公钥
cat ~/.ssh/id_ed25519.pub

# 3. 将公钥添加到 GitHub
#    GitHub → Settings → SSH and GPG keys → New SSH key

# 4. 测试连接
ssh -T git@github.com
```

### 4.2 拉取时提示冲突

```bash
# 错误信息: Automatic merge failed; fix conflicts and then commit the result

# 解决方案：保留远程版本
git reset --hard origin/main

# 或者：保留本地版本
git pull --rebase origin main
```

### 4.3 宝塔面板 Git 插件无法使用

**解决方案：手动使用命令行**

```bash
# 1. 安装 Git（如果未安装）
yum install git -y  # CentOS
# 或
apt install git -y  # Ubuntu/Debian

# 2. 配置 Git 用户信息
git config --global user.name "Your Name"
git config --global user.email "your_email@example.com"

# 3. 拉取代码
cd /www/wwwroot/binaryecom20
git pull origin main
```

### 4.4 文件权限问题

```bash
# 拉取后文件权限不正确

# 解决方案：修复权限
cd /www/wwwroot/binaryecom20

# 设置目录权限
find Files/core -type d -exec chmod 755 {} \;

# 设置文件权限
find Files/core -type f -exec chmod 644 {} \;

# 设置所有者
chown -R www:www /www/wwwroot/binaryecom20
```

---

## 五、快速同步流程

### 5.1 本地推送（3 步）

```bash
cd /www/wwwroot/binaryecom20
git add .
git commit -m "更新说明"
git push origin main
```

### 5.2 云服务器拉取（2 步）

```bash
# 方式一：宝塔终端
cd /www/wwwroot/binaryecom20
git pull origin main

# 方式二：SSH 连接
ssh -6 root@[IPv6地址]
cd /www/wwwroot/binaryecom20
git pull origin main
```

---

## 六、自动化同步（可选）

### 6.1 使用 Webhook 自动部署

在 GitHub 配置 Webhook，推送代码后自动触发服务器拉取：

```php
// webhook.php - 放在云服务器上
<?php
$secret = 'your_webhook_secret';
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE'];
$payload = file_get_contents('php://input');

if (hash_equals('sha1=' . hash_hmac('sha1', $payload, $secret), $signature)) {
    shell_exec('cd /www/wwwroot/binaryecom20 && git pull origin main 2>&1');
    echo 'Deployment successful';
} else {
    http_response_code(403);
    echo 'Invalid signature';
}
?>
```

### 6.2 使用定时任务同步

在宝塔面板添加定时任务：

```bash
# 每 10 分钟检查一次更新
*/10 * * * * cd /www/wwwroot/binaryecom20 && git pull origin main
```

---

## 附录

### A. 常用 Git 命令

```bash
# 查看状态
git status

# 查看日志
git log --oneline

# 查看远程仓库
git remote -v

# 查看分支
git branch -a

# 切换分支
git checkout main

# 创建并切换分支
git checkout -b feature-branch

# 删除分支
git branch -d feature-branch

# 撤销本地修改
git checkout -- filename

# 撤销所有本地修改
git reset --hard HEAD
```

### B. IPv6 地址格式

```
完整格式: 2001:0db8:85a3:0000:0000:8a2e:0370:7334
简化格式: 2001:db8:85a3::8a2e:370:7334

在 URL 中使用（需要方括号）:
http://[2001:db8::1]:8080/
ssh root@[2001:db8::1]
```

---

**文档版本**: v1.0  
**最后更新**: 2025-12-26
