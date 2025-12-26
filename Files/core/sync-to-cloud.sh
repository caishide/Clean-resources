#!/bin/bash

################################################################################
# Git 自动同步脚本 - 从本地服务器同步到云服务器
# 
# 功能：
# 1. 自动检测 Git 仓库中修改的文件
# 2. 使用 rsync 增量同步到云服务器
# 3. 支持排除文件和目录
# 4. 显示同步进度和结果
################################################################################

# ==================== 配置区域 ====================

# 云服务器配置
REMOTE_USER="root"
REMOTE_HOST="240e:95d:c01:700::4:2a9c"  # IPv6 地址
REMOTE_PORT="22"  # SSH 端口（默认 22，如果不同请修改）
REMOTE_PATH="/www/wwwroot/h2-home.cn"

# SSH 连接选项（IPv6 需要特殊处理）
SSH_OPTS="-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ConnectTimeout=10"

# SSH 密钥配置（如果使用密钥认证，取消注释并设置）
# SSH_KEY_PATH="$HOME/.ssh/id_rsa"  # SSH 私钥路径
# if [ -n "$SSH_KEY_PATH" ]; then
#     SSH_OPTS="$SSH_OPTS -i $SSH_KEY_PATH"
# fi

# 本地项目路径
LOCAL_PATH="/www/wwwroot/binaryecom20"

# 排除不同步的文件和目录（用空格分隔）
EXCLUDE_FILES="--exclude='.git' --exclude='node_modules' --exclude='storage/logs/*' --exclude='storage/framework/cache/*' --exclude='.env' --exclude='vendor'"

# ==================== 函数定义 ====================

# 显示使用说明
show_usage() {
    echo "=========================================="
    echo "  Git 自动同步脚本"
    echo "=========================================="
    echo ""
    echo "用法: $0 [选项]"
    echo ""
    echo "选项:"
    echo "  -h, --help     显示帮助信息"
    echo "  -d, --dry-run  预演模式（不实际传输）"
    echo "  -v, --verbose  显示详细输出"
    echo "  -y, --yes      自动确认（不询问）"
    echo ""
    echo "示例:"
    echo "  $0              # 交互式同步"
    echo "  $0 -d           # 预演模式"
    echo "  $0 -y           # 自动同步"
    echo ""
}

# 检查 rsync 是否安装
check_rsync() {
    if ! command -v rsync &> /dev/null; then
        echo "❌ 错误: rsync 未安装"
        echo "请先安装 rsync:"
        echo "  CentOS: yum install rsync -y"
        echo "  Ubuntu: apt install rsync -y"
        exit 1
    fi
}

# 检查 IPv6 连接
check_ipv6_connection() {
    echo "正在检查 IPv6 连接..."
    if ping6 -c 1 $REMOTE_HOST &> /dev/null; then
        echo "✅ IPv6 连接正常"
        return 0
    else
        echo "⚠️  警告: 无法 ping 通 IPv6 地址"
        echo "但这可能是正常的（某些服务器禁用了 ICMP）"
        return 0
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
        find . -type f ! -path './.git/*' ! -path './node_modules/*' ! -path './vendor/*'
    else
        # 获取修改的文件
        echo "获取自上次提交以来修改的文件..."
        git diff --name-only $LAST_COMMIT HEAD 2>/dev/null || echo ""
    fi
}

# 执行同步
do_sync() {
    local dry_run=$1
    local verbose=$2
    
    echo ""
    echo "=========================================="
    echo "  开始同步"
    echo "=========================================="
    echo "本地路径: $LOCAL_PATH"
    echo "远程路径: $REMOTE_USER@[$REMOTE_HOST]:$REMOTE_PATH"
    echo ""
    
    # 构建 rsync 命令（IPv6 需要使用 -e 指定 SSH）
    RSYNC_CMD="rsync -avz --progress -e 'ssh -p ${REMOTE_PORT} ${SSH_OPTS}'"
    
    if [ "$dry_run" = "true" ]; then
        RSYNC_CMD="$RSYNC_CMD --dry-run"
        echo "🔍 预演模式（不会实际传输文件）"
    fi
    
    if [ "$verbose" = "true" ]; then
        RSYNC_CMD="$RSYNC_CMD -vv"
    fi
    
    # 添加排除项
    RSYNC_CMD="$RSYNC_CMD $EXCLUDE_FILES"
    
    # 添加源和目标（IPv6 地址需要用方括号包裹）
    RSYNC_CMD="$RSYNC_CMD $LOCAL_PATH/ $REMOTE_USER@[$REMOTE_HOST]:$REMOTE_PATH/"
    
    echo "执行命令: $RSYNC_CMD"
    echo ""
    echo "=========================================="
    
    # 执行同步
    eval $RSYNC_CMD
    
    local exit_code=$?
    
    echo ""
    echo "=========================================="
    if [ $exit_code -eq 0 ]; then
        echo "✅ 同步成功！"
    else
        echo "❌ 同步失败（退出码: $exit_code）"
    fi
    echo "=========================================="
}

# ==================== 主程序 ====================

# 默认选项
DRY_RUN=false
VERBOSE=false
AUTO_YES=false

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
        -y|--yes)
            AUTO_YES=true
            shift
            ;;
        *)
            echo "未知选项: $1"
            show_usage
            exit 1
            ;;
    esac
done

# 显示配置
echo "=========================================="
echo "  Git 自动同步脚本"
echo "=========================================="
echo "本地路径: $LOCAL_PATH"
echo "远程服务器: $REMOTE_USER@[$REMOTE_HOST]:$REMOTE_PORT"
echo "远程路径: $REMOTE_PATH"
echo "宝塔面板: http://[$REMOTE_HOST]:13040/bfe8cfc3"
echo "=========================================="
echo ""

# 检查 rsync
check_rsync

# 检查 IPv6 连接
check_ipv6_connection

# 显示修改的文件
echo ""
echo "=========================================="
echo "  检查修改的文件"
echo "=========================================="
CHANGED_FILES=$(get_changed_files)
CHANGED_COUNT=$(echo "$CHANGED_FILES" | grep -v '^$' | wc -l)

if [ $CHANGED_COUNT -eq 0 ]; then
    echo "没有检测到文件修改"
    echo "提示: 如果这是首次同步，将同步所有文件"
else
    echo "检测到 $CHANGED_COUNT 个修改的文件:"
    echo "$CHANGED_FILES" | head -20
    if [ $CHANGED_COUNT -gt 20 ]; then
        echo "... 还有 $((CHANGED_COUNT - 20)) 个文件"
    fi
fi
echo "=========================================="
echo ""

# 确认同步
if [ "$AUTO_YES" = "false" ] && [ "$DRY_RUN" = "false" ]; then
    read -p "是否继续同步? (yes/no): " confirm
    if [ "$confirm" != "yes" ]; then
        echo "已取消同步"
        exit 0
    fi
fi

# 执行同步
do_sync "$DRY_RUN" "$VERBOSE"

# 显示后续步骤
echo ""
echo "=========================================="
echo "  后续步骤"
echo "=========================================="
echo "1. 登录云服务器"
echo "2. 进入项目目录: cd $REMOTE_PATH"
echo "3. 检查文件: ls -la Files/core/"
echo "4. 如需执行迁移: php artisan migrate --force"
echo "=========================================="
