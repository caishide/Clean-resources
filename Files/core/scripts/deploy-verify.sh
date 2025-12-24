#!/bin/bash

# =============================================================================
# BinaryEcom20 部署验证脚本
# =============================================================================
# 用法: bash scripts/deploy-verify.sh
# =============================================================================

set -e

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}BinaryEcom20 部署验证${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

# 计数器
PASSED=0
FAILED=0
WARNINGS=0

# 测试函数
pass() {
    echo -e "  ${GREEN}✓${NC} $1"
    ((PASSED++))
}

fail() {
    echo -e "  ${RED}✗${NC} $1"
    ((FAILED++))
}

warn() {
    echo -e "  ${YELLOW}!${NC} $1"
    ((WARNINGS++))
}

section() {
    echo ""
    echo -e "${YELLOW}▶ $1${NC}"
    echo "----------------------------------------"
}

# 1. 环境检查
section "环境检查"

# PHP版本
PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
REQUIRED_VERSION="8.3"
if [[ "$(printf '%s\n' "$REQUIRED_VERSION" "$PHP_VERSION" | sort -V | head -n1)" == "$REQUIRED_VERSION" ]]; then
    pass "PHP版本: $PHP_VERSION (>= $REQUIRED_VERSION)"
else
    fail "PHP版本: $PHP_VERSION (需要 >= $REQUIRED_VERSION)"
fi

# 必需扩展
REQUIRED_EXTENSIONS=("pdo_mysql" "mbstring" "xml" "bcmath" "openssl" "tokenizer" "json" "curl" "gd" "redis")
for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^${ext}$"; then
        pass "扩展: $ext"
    else
        if [[ "$ext" == "redis" ]]; then
            warn "扩展: $ext (推荐但非必需)"
        else
            fail "扩展: $ext (必需)"
        fi
    fi
done

# 2. 文件权限检查
section "文件权限检查"

WRITEABLE_DIRS=("storage" "storage/logs" "storage/framework" "storage/framework/cache" "storage/framework/sessions" "storage/framework/views" "bootstrap/cache" "storage/app/public")

for dir in "${WRITEABLE_DIRS[@]}"; do
    if [ -w "$PROJECT_DIR/$dir" ]; then
        pass "可写: $dir"
    else
        fail "可写: $dir"
    fi
done

# 3. 配置文件检查
section "配置文件检查"

if [ -f "$PROJECT_DIR/.env" ]; then
    pass ".env文件存在"
else
    fail ".env文件不存在"
fi

# 检查关键配置
check_env() {
    local key=$1
    local value=$(grep "^${key}=" "$PROJECT_DIR/.env" | cut -d'=' -f2 | tr -d ' "')
    if [ -n "$value" ]; then
        pass "配置: $key"
    else
        fail "配置: $key"
    fi
}

check_env "APP_ENV"
check_env "APP_DEBUG" "应该为false"
check_env "DB_CONNECTION"
check_env "REDIS_HOST"
check_env "QUEUE_CONNECTION" "应该为redis"

# 4. 依赖检查
section "依赖检查"

if [ -f "$PROJECT_DIR/composer.lock" ]; then
    pass "composer.lock存在"
else
    fail "composer.lock不存在"
fi

if [ -d "$PROJECT_DIR/vendor" ]; then
    pass "vendor目录存在"
else
    warn "vendor目录不存在，需要运行 composer install"
fi

# 5. 数据库检查
section "数据库检查"

if php artisan db:show > /dev/null 2>&1; then
    pass "数据库连接正常"
else
    warn "数据库连接失败，请检查配置"
fi

# 6. 缓存检查
section "缓存检查"

if php artisan config:clear > /dev/null 2>&1; then
    pass " artisan命令正常"
else
    fail " artisan命令失败"
fi

# 7. 路由检查
section "路由检查"

if php artisan route:list > /dev/null 2>&1; then
    ROUTE_COUNT=$(php artisan route:list --json 2>/dev/null | grep -o '"' | wc -l)
    if [ $ROUTE_COUNT -gt 0 ]; then
        pass "路由加载正常"
    else
        warn "路由加载可能有问题"
    fi
else
    warn "路由命令执行失败"
fi

# 8. 安全检查
section "安全检查"

if [ "$(grep -c "APP_DEBUG=false" .env 2>/dev/null || echo 0)" -eq 1 ]; then
    pass "APP_DEBUG已关闭"
else
    fail "APP_DEBUG应该为false"
fi

if [ -f "$PROJECT_DIR/config/cors.php" ]; then
    CORS_ORIGINS=$(grep -o "'allowed_origins'" "$PROJECT_DIR/config/cors.php" -A 3 | grep -o "'\*'" || echo "")
    if [ "$CORS_ORIGINS" == "'*'" ]; then
        warn "CORS允许所有来源，生产环境应限制"
    else
        pass "CORS已配置来源限制"
    fi
fi

# 9. 性能配置检查
section "性能配置检查"

if grep -q "QUEUE_CONNECTION=redis" .env; then
    pass "队列使用Redis"
else
    warn "队列未使用Redis (性能可能受影响)"
fi

if grep -q "SESSION_DRIVER=redis" .env; then
    pass "会话使用Redis"
else
    warn "会话未使用Redis (性能可能受影响)"
fi

if grep -q "CACHE_DRIVER=redis" .env; then
    pass "缓存使用Redis"
else
    warn "缓存未使用Redis (性能可能受影响)"
fi

# 总结
section "验证总结"

TOTAL=$((PASSED + FAILED + WARNINGS))
echo ""
echo -e "总数: $TOTAL 项"
echo -e "${GREEN}通过: $PASSED${NC} 项"
echo -e "${RED}失败: $FAILED${NC} 项"
echo -e "${YELLOW}警告: $WARNINGS${NC} 项"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ 部署验证通过！${NC}"
    exit 0
else
    echo -e "${RED}✗ 部署验证失败，请修复上述问题${NC}"
    exit 1
fi
