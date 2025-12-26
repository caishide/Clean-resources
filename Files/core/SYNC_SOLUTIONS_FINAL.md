# 云服务器代码同步解决方案 - 最终版

## 问题确认

### 网络环境

| 设备 | IPv4 | IPv6 | Git 访问 |
|------|------|------|----------|
| **本地开发服务器** | ✅ 192.168.1.17 | ❌ 无 | ✅ GitHub |
| **云服务器** | ❌ 无 | ✅ 240e:95d:c01:700::4:2a9c | ❌ GitHub/Gitee |

### 核心问题

1. **本地开发服务器**（IPv4 only）无法直接连接云服务器（IPv6 only）
2. **云服务器**（IPv6 only）无法访问 GitHub 或 Gitee
3. 云服务器已有 Git 仓库，但 remote 指向 Gitee（无法连接）

---

## 推荐方案：宝塔面板文件上传 + 本地 Git 管理

由于云服务器无法访问任何 Git 托管平台，最可靠的方案是：

**本地使用 Git 管理代码 → 打包 → 通过宝塔面板上传 → 云服务器解压**

### 完整步骤

#### 第一步：在本地开发服务器上创建同步脚本

创建 `/www/wwwroot/binaryecom20/Files/core/sync-package.sh`：

```bash
#!/bin/bash

################################################################################
# 代码打包同步脚本
# 功能：自动检测 Git 修改的文件并打包，方便通过宝塔面板上传
################################################################################

# 配置
LOCAL_PATH="/www/wwwroot/binaryecom20"
TEMP_DIR="/tmp/sync-package-$$"
PACKAGE_FILE="/tmp/sync-files-$(date +%Y%m%d_%H%M%S).tar.gz"

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "=========================================="
echo "  代码打包同步脚本"
echo "=========================================="
echo ""

# 获取 Git 修改的文件
cd "$LOCAL_PATH"
LAST_COMMIT=$(git rev-parse HEAD~1 2>/dev/null)

if [ -z "$LAST_COMMIT" ]; then
    echo -e "${YELLOW}首次同步，将打包所有文件${NC}"
    FILES=$(find . -type f ! -path './.git/*' ! -path './node_modules/*' ! -path './vendor/*' ! -path './storage/logs/*' ! -path './storage/framework/*' ! -path './.idea/*')
else
    echo "获取自上次提交以来修改的文件..."
    FILES=$(git diff --name-only $LAST_COMMIT HEAD 2>/dev/null)
fi

# 统计文件数量
FILE_COUNT=$(echo "$FILES" | grep -v '^$' | wc -l)

if [ $FILE_COUNT -eq 0 ]; then
    echo -e "${YELLOW}没有检测到文件修改${NC}"
    exit 0
fi

echo -e "${GREEN}检测到 $FILE_COUNT 个修改的文件${NC}"
echo ""

# 创建临时目录
mkdir -p "$TEMP_DIR"

# 复制文件到临时目录
echo "正在复制文件..."
echo "$FILES" | while read file; do
    if [ -n "$file" ] && [ -f "$LOCAL_PATH/$file" ]; then
        mkdir -p "$TEMP_DIR/$(dirname "$file")"
        cp "$LOCAL_PATH/$file" "$TEMP_DIR/$file"
        echo "  ✓ $file"
    fi
done

# 创建压缩包
echo ""
echo "正在创建压缩包..."
cd "$TEMP_DIR"
tar -czf "$PACKAGE_FILE" . 2>/dev/null

if [ $? -eq 0 ]; then
    SIZE=$(du -h "$PACKAGE_FILE" | cut -f1)
    echo -e "${GREEN}✅ 压缩包创建成功${NC}"
    echo "文件路径: $PACKAGE_FILE"
    echo "文件大小: $SIZE"
    echo ""
    echo "=========================================="
    echo "  下一步操作"
    echo "=========================================="
    echo "1. 登录宝塔面板"
    echo "   URL: http://[240e:95d:c01:700::4:2a9c]:13040/bfe8cfc3"
    echo ""
    echo "2. 进入文件管理"
    echo "   路径: /www/wwwroot/h2-home.cn"
    echo ""
    echo "3. 上传压缩包"
    echo "   文件: $PACKAGE_FILE"
    echo ""
    echo "4. 解压文件"
    echo "   右键点击压缩包 → 解压"
    echo ""
    echo "5. 执行数据库迁移（如需要）"
    echo "   在宝塔终端执行:"
    echo "   cd /www/wwwroot/h2-home.cn/Files/core"
    echo "   php artisan migrate --force"
    echo "=========================================="
else
    echo -e "${RED}❌ 压缩包创建失败${NC}"
    exit 1
fi

# 清理临时文件
rm -rf "$TEMP_DIR"
```

设置执行权限：
```bash
chmod +x /www/wwwroot/binaryecom20/Files/core/sync-package.sh
```

#### 第二步：日常使用流程

```bash
# 1. 本地开发
cd /www/wwwroot/binaryecom20
# ... 修改代码 ...

# 2. Git 提交
git add .
git commit -m "update: 描述修改内容"
git push origin master

# 3. 创建同步包
/www/wwwroot/binaryecom20/Files/core/sync-package.sh

# 4. 通过宝塔面板上传生成的压缩包
#    脚本会显示压缩包路径，例如：/tmp/sync-files-20251226_134500.tar.gz

# 5. 在云服务器上解压
#    通过宝塔面板文件管理器上传后，右键解压
```

---

## 方案二：使用 Git Bundle（推荐用于首次同步）

如果需要首次同步大量代码，可以使用 Git Bundle 方式。

### 步骤

#### 1. 在本地开发服务器创建 Git Bundle

```bash
cd /www/wwwroot/binaryecom20
git bundle create /tmp/binaryecom20.bundle --all
```

#### 2. 通过宝塔面板上传 bundle 文件

上传 `/tmp/binaryecom20.bundle` 到云服务器的 `/tmp/` 目录

#### 3. 在云服务器上从 bundle 克隆

```bash
cd /www/wwwroot
rm -rf h2-home.cn  # 备份后删除旧目录
git clone /tmp/binaryecom20.bundle h2-home.cn
```

#### 4. 后续更新使用打包脚本

```bash
# 本地
/www/wwwroot/binaryecom20/Files/core/sync-package.sh

# 通过宝塔面板上传并解压
```

---

## 方案三：配置本地 HTTP 服务器（高级方案）

如果需要更自动化的方案，可以在本地开发服务器上搭建 HTTP 服务器，云服务器通过 HTTP 下载更新包。

### 步骤

#### 1. 在本地开发服务器创建 HTTP 服务

```bash
# 创建同步目录
mkdir -p /www/wwwroot/sync-repo

# 创建更新脚本
cat > /www/wwwroot/binaryecom20/Files/core/sync-http.sh << 'EOF'
#!/bin/bash
SYNC_DIR="/www/wwwroot/sync-repo"
LOCAL_PATH="/www/wwwroot/binaryecom20"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# 获取修改的文件
cd "$LOCAL_PATH"
LAST_COMMIT=$(git rev-parse HEAD~1 2>/dev/null)

if [ -z "$LAST_COMMIT" ]; then
    FILES=$(find . -type f ! -path './.git/*' ! -path './node_modules/*' ! -path './vendor/*')
else
    FILES=$(git diff --name-only $LAST_COMMIT HEAD 2>/dev/null)
fi

# 创建临时目录
TEMP_DIR="$SYNC_DIR/temp-$$"
mkdir -p "$TEMP_DIR"

# 复制文件
echo "$FILES" | while read file; do
    if [ -n "$file" ] && [ -f "$LOCAL_PATH/$file" ]; then
        mkdir -p "$TEMP_DIR/$(dirname "$file")"
        cp "$LOCAL_PATH/$file" "$TEMP_DIR/$file"
    fi
done

# 创建压缩包
PACKAGE_FILE="$SYNC_DIR/sync-$TIMESTAMP.tar.gz"
cd "$TEMP_DIR"
tar -czf "$PACKAGE_FILE" .

# 创建版本信息文件
cat > "$SYNC_DIR/latest.json" << JSON
{
  "timestamp": "$TIMESTAMP",
  "file": "sync-$TIMESTAMP.tar.gz",
  "commit": "$(git rev-parse HEAD)"
}
JSON

# 清理
rm -rf "$TEMP_DIR"

echo "✅ 更新包已创建: $PACKAGE_FILE"
echo "下载地址: http://192.168.1.17/sync-repo/sync-$TIMESTAMP.tar.gz"
EOF

chmod +x /www/wwwroot/binaryecom20/Files/core/sync-http.sh
```

#### 2. 配置 Nginx 提供 HTTP 访问

在本地开发服务器的 Nginx 配置中添加：

```nginx
location /sync-repo/ {
    alias /www/wwwroot/sync-repo/;
    autoindex on;
    allow all;
}
```

#### 3. 在云服务器上创建下载脚本

```bash
cat > /www/wwwroot/h2-home.cn/auto-update.sh << 'EOF'
#!/bin/bash
SYNC_URL="http://192.168.1.17/sync-repo"
TEMP_DIR="/tmp/sync-update-$$"

# 获取最新版本信息
curl -s "$SYNC_URL/latest.json" > /tmp/latest.json

if [ ! -f /tmp/latest.json ]; then
    echo "无法获取版本信息"
    exit 1
fi

# 解析 JSON（需要 jq）
FILE=$(grep -o '"file": "[^"]*"' /tmp/latest.json | cut -d'"' -f4)

if [ -z "$FILE" ]; then
    echo "无法解析版本信息"
    exit 1
fi

# 下载更新包
echo "正在下载更新包..."
mkdir -p "$TEMP_DIR"
cd "$TEMP_DIR"
curl -O "$SYNC_URL/$FILE"

# 解压
echo "正在解压..."
tar -xzf "$FILE"

# 复制文件
echo "正在更新文件..."
cp -rf ./* /www/wwwroot/h2-home.cn/

# 清理
rm -rf "$TEMP_DIR"

echo "✅ 更新完成"
EOF

chmod +x /www/wwwroot/h2-home.cn/auto-update.sh
```

#### 4. 设置定时任务

在宝塔面板中添加定时任务，执行 `/www/wwwroot/h2-home.cn/auto-update.sh`

---

## 快速参考

### 服务器信息

```
本地开发服务器: 192.168.1.17 (IPv4 only)
云服务器: 240e:95d:c01:700::4:2a9c (IPv6 only)
宝塔面板: http://[240e:95d:c01:700::4:2a9c]:13040/bfe8cfc3
```

### 推荐工作流程

```bash
# 本地开发
cd /www/wwwroot/binaryecom20
git add .
git commit -m "update"
git push origin master

# 创建同步包
/www/wwwroot/binaryecom20/Files/core/sync-package.sh

# 通过宝塔面板上传并解压
```

### 相关文件

- 本地项目路径：`/www/wwwroot/binaryecom20`
- 云服务器路径：`/www/wwwroot/h2-home.cn`
- 同步脚本：`/www/wwwroot/binaryecom20/Files/core/sync-package.sh`
