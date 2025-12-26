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
BLUE='\033[0;34m'
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
echo -e "${BLUE}正在复制文件...${NC}"
echo "$FILES" | while read file; do
    if [ -n "$file" ] && [ -f "$LOCAL_PATH/$file" ]; then
        mkdir -p "$TEMP_DIR/$(dirname "$file")"
        cp "$LOCAL_PATH/$file" "$TEMP_DIR/$file"
        echo "  ✓ $file"
    fi
done

# 创建压缩包
echo ""
echo -e "${BLUE}正在创建压缩包...${NC}"
cd "$TEMP_DIR"
tar -czf "$PACKAGE_FILE" . 2>/dev/null

if [ $? -eq 0 ]; then
    SIZE=$(du -h "$PACKAGE_FILE" | cut -f1)
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}✅ 压缩包创建成功${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo -e "文件路径: ${BLUE}$PACKAGE_FILE${NC}"
    echo -e "文件大小: ${BLUE}$SIZE${NC}"
    echo ""
    echo -e "${YELLOW}========================================${NC}"
    echo -e "${YELLOW}  下一步操作${NC}"
    echo -e "${YELLOW}========================================${NC}"
    echo -e "1. 登录宝塔面板"
    echo -e "   ${BLUE}URL: http://[240e:95d:c01:700::4:2a9c]:13040/bfe8cfc3${NC}"
    echo ""
    echo -e "2. 进入文件管理"
    echo -e "   ${BLUE}路径: /www/wwwroot/h2-home.cn${NC}"
    echo ""
    echo -e "3. 上传压缩包"
    echo -e "   ${BLUE}本地文件: $PACKAGE_FILE${NC}"
    echo -e "   ${BLUE}上传到: /www/wwwroot/h2-home.cn/${NC}"
    echo ""
    echo -e "4. 解压文件"
    echo -e "   ${BLUE}右键点击压缩包 → 解压到当前目录${NC}"
    echo ""
    echo -e "5. 执行数据库迁移（如需要）"
    echo -e "   ${BLUE}在宝塔终端执行:${NC}"
    echo -e "   ${BLUE}cd /www/wwwroot/h2-home.cn/Files/core${NC}"
    echo -e "   ${BLUE}php artisan migrate --force${NC}"
    echo -e "${YELLOW}========================================${NC}"
else
    echo ""
    echo -e "${RED}========================================${NC}"
    echo -e "${RED}❌ 压缩包创建失败${NC}"
    echo -e "${RED}========================================${NC}"
    exit 1
fi

# 清理临时文件
rm -rf "$TEMP_DIR"
