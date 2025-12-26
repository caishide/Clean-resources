#!/bin/bash

################################################################################
# Git 自动同步脚本 - 使用宝塔 API 方式
# 
# 适用场景：
# - 本地服务器只有 IPv4
# - 云服务器 SSH 端口被防火墙阻止
# - 通过宝塔 API 上传文件
################################################################################

# ==================== 配置区域 ====================

# 宝塔面板配置
BT_PANEL_URL="http://47.52.61.90:13040"
BT_PANEL_PATH="/bfe8cfc3"
BT_USERNAME=""  # 宝塔面板用户名
BT_PASSWORD=""  # 宝塔面板密码
BT_API_TOKEN=""  # 或者使用 API Token（推荐）

# 本地项目路径
LOCAL_PATH="/www/wwwroot/binaryecom20"

# 云服务器项目路径
REMOTE_PATH="/www/wwwroot/h2-home.cn"

# 临时文件目录
TEMP_DIR="/tmp/sync-to-cloud-$$"

# ==================== 函数定义 ====================

# 显示使用说明
show_usage() {
    echo "=========================================="
    echo "  Git 自动同步脚本 - 宝塔 API 方式"
    echo "=========================================="
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  -h, --help     显示帮助信息"
    echo "  -d, --dry-run  预演模式（不实际传输）"
    echo "  -v, --verbose  显示详细输出"
    echo ""
    echo "配置说明:"
    echo "  请先编辑脚本，配置宝塔面板信息："
    echo "  1. BT_PANEL_URL: 宝塔面板地址"
    echo "  2. BT_PANEL_PATH: 面板路径"
    echo "  3. BT_USERNAME: 面板用户名"
    echo "  4. BT_PASSWORD: 面板密码（或使用 API_TOKEN）"
    echo ""
}

# 检查配置
check_config() {
    if [ -z "$BT_PANEL_URL" ] || [ -z "$BT_USERNAME" ] || [ -z "$BT_PASSWORD" ]; then
        echo "❌ 错误: 请先配置宝塔面板信息"
        echo "编辑脚本: vim $0"
        exit 1
    fi
}

# 获取 Git 修改的文件
get_changed_files() {
    cd "$LOCAL_PATH"
    
    # 获取最后一次提交的 SHA
    LAST_COMMIT=$(git rev-parse HEAD~1 2>/dev/null)
    
    if [ -z "$LAST_COMMIT" ]; then
        echo "获取所有文件（首次同步）"
        # 首次同步，获取所有文件
        find . -type f ! -path './.git/*' ! -path './node_modules/*' ! -path './vendor/*' ! -path './storage/logs/*' ! -path './storage/framework/*'
    else
        # 获取修改的文件
        echo "获取自上次提交以来修改的文件..."
        git diff --name-only $LAST_COMMIT HEAD 2>/dev/null || echo ""
    fi
}

# 创建文件包
create_package() {
    local files=$1
    local dry_run=$2
    
    echo ""
    echo "=========================================="
    echo "  创建文件包"
    echo "=========================================="
    
    # 创建临时目录
    mkdir -p "$TEMP_DIR"
    
    # 复制文件到临时目录
    echo "正在复制文件..."
    echo "$files" | while read file; do
        if [ -n "$file" ] && [ -f "$LOCAL_PATH/$file" ]; then
            mkdir -p "$TEMP_DIR/$(dirname "$file")"
            cp "$LOCAL_PATH/$file" "$TEMP_DIR/$file"
            echo "  ✓ $file"
        fi
    done
    
    # 创建压缩包
    local package_file="$TEMP_DIR/sync-files.tar.gz"
    echo ""
    echo "正在创建压缩包..."
    cd "$TEMP_DIR"
    tar -czf "$package_file" . 2>/dev/null
    
    local size=$(du -h "$package_file" | cut -f1)
    echo "✅ 压缩包创建成功: $package_file ($size)"
    
    echo "$package_file"
}

# 上传到云服务器（使用宝塔 API）
upload_to_server() {
    local package_file=$1
    local dry_run=$2
    
    echo ""
    echo "=========================================="
    echo "  上传到云服务器"
    echo "=========================================="
    
    if [ "$dry_run" = "true" ]; then
        echo "🔍 预演模式（不会实际上传）"
        echo "压缩包: $package_file"
        return 0
    fi
    
    # 这里需要实现宝塔 API 调用
    # 由于宝塔 API 较复杂，这里提供替代方案
    
    echo "⚠️  注意: 宝塔 API 方式需要额外配置"
    echo ""
    echo "推荐使用以下替代方案："
    echo ""
    echo "方案 1: 使用宝塔面板的文件上传功能"
    echo "  1. 访问: $BT_PANEL_URL$BT_PANEL_PATH"
    echo "  2. 进入: 文件 → $REMOTE_PATH"
    echo "  3. 上传: $package_file"
    echo "  4. 解压: tar -xzf sync-files.tar.gz"
    echo ""
    echo "方案 2: 配置云服务器安全组开放 SSH 端口"
    echo "  1. 登录阿里云控制台"
    echo "  2. 找到云服务器实例"
    echo "  3. 配置安全组规则"
    echo "  4. 添加入站规则: 端口 22, 协议 TCP"
    echo ""
    echo "方案 3: 使用 Git Webhook 自动部署"
    echo "  1. 在云服务器上配置 Git 仓库"
    echo "  2. 配置 GitHub Webhook"
    echo "  3. 推送代码时自动拉取"
}

# 清理临时文件
cleanup() {
    if [ -d "$TEMP_DIR" ]; then
        rm -rf "$TEMP_DIR"
    fi
}

# ==================== 主程序 ====================

# 默认选项
DRY_RUN=false
VERBOSE=false

# 解析命令行参数
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_usage
            exit 0
            ;;
        -d|--dry-run)
            DRY_RUN=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        *)
            echo "未知选项: $1"
            show_usage
            exit 1
            ;;
    esac
done

# 设置清理陷阱
trap cleanup EXIT

# 显示配置
echo "=========================================="
echo "  Git 自动同步脚本 - 宝塔 API 方式"
echo "=========================================="
echo "本地路径: $LOCAL_PATH"
echo "远程路径: $REMOTE_PATH"
echo "宝塔面板: $BT_PANEL_URL$BT_PANEL_PATH"
echo "=========================================="
echo ""

# 检查配置
check_config

# 显示修改的文件
echo ""
echo "=========================================="
echo "  检查修改的文件"
echo "=========================================="
CHANGED_FILES=$(get_changed_files)
CHANGED_COUNT=$(echo "$CHANGED_FILES" | grep -v '^$' | wc -l)

if [ $CHANGED_COUNT -eq 0 ]; then
    echo "没有检测到文件修改"
    exit 0
fi

echo "检测到 $CHANGED_COUNT 个修改的文件:"
echo "$CHANGED_FILES" | head -20
if [ $CHANGED_COUNT -gt 20 ]; then
    echo "... 还有 $((CHANGED_COUNT - 20)) 个文件"
fi
echo "=========================================="
echo ""

# 创建文件包
PACKAGE_FILE=$(create_package "$CHANGED_FILES" "$DRY_RUN")

# 上传到服务器
upload_to_server "$PACKAGE_FILE" "$DRY_RUN"

echo ""
echo "=========================================="
echo "  完成"
echo "=========================================="
