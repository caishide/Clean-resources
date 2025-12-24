#!/bin/bash

# =============================================================================
# BinaryEcom20 性能基准测试
# =============================================================================
# 用法: bash scripts/performance-benchmark.sh
# =============================================================================

set -e

# 配置
BASE_URL="${1:-http://localhost}"
ITERATIONS="${2:-5}"
OUTPUT_FILE="storage/performance-benchmark-$(date +%Y%m%d_%H%M%S).json"

# 颜色
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}BinaryEcom20 性能基准测试${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "基础URL: $BASE_URL"
echo "测试次数: $ITERATIONS"
echo ""

# 初始化结果
declare -A results
total_time=0

# 测试函数
test_endpoint() {
    local name=$1
    local url=$2
    local times=()

    echo -e "${YELLOW}测试: $name${NC}"

    for i in $(seq 1 $ITERATIONS); do
        # 测量响应时间(秒)
        time_taken=$(curl -o /dev/null -s -w "%{time_total}" -H "Accept: image/svg+xml" "$url" 2>/dev/null || echo "0")

        # 处理科学计数法
        time_ms=$(echo "$time_taken * 1000" | bc 2>/dev/null || echo "0")

        if [ -n "$time_ms" ] && [ "$time_ms" != "0" ]; then
            times+=("$time_ms")
            echo "  第$i次: ${time_ms}ms"
        fi
    done

    if [ ${#times[@]} -gt 0 ]; then
        # 计算平均时间
        sum=0
        for t in "${times[@]}"; do
            sum=$(echo "$sum + $t" | bc)
        done
        avg=$(echo "scale=2; $sum / ${#times[@]}" | bc)

        # 存储结果
        results["$name"]=$avg
        echo -e "  ${GREEN}平均: ${avg}ms${NC}"
    else
        results["$name"]="N/A"
        echo -e "  ${RED}测试失败${NC}"
    fi
    echo ""
}

# 测试页面
echo "正在测试页面加载速度..."
echo "----------------------------------------"

test_endpoint "首页" "$BASE_URL/"
test_endpoint "占位图片(50x50)" "$BASE_URL/placeholder-image/50x50"
test_endpoint "占位图片(200x200)" "$BASE_URL/placeholder-image/200x200"

# 生成JSON报告
generate_json() {
    local json="{
  \"test_info\": {
    \"base_url\": \"$BASE_URL\",
    \"iterations\": $ITERATIONS,
    \"timestamp\": \"$(date -Iseconds)\",
    \"php_version\": \"$(php -r 'echo PHP_MAJOR_VERSION . \".\" . PHP_MINOR_VERSION;' 2>/dev/null || echo 'N/A')\",
    \"laravel_version\": \"$(php artisan about 2>/dev/null | grep 'Laravel Version' | awk '{print $NF}' || echo 'N/A')\"
  },
  \"results\": {"

    local first=true
    for key in "${!results[@]}"; do
        if [ "$first" = true ]; then
            first=false
        else
            json+=","
        fi
        json+="
    \"$key\": ${results[$key]}"
    done

    json+="
  },
  \"summary\": {"

    # 计算总体平均
    total=0
    count=0
    for val in "${results[@]}"; do
        if [ "$val" != "N/A" ]; then
            total=$(echo "$total + $val" | bc)
            count=$((count + 1))
        fi
    done

    if [ $count -gt 0 ]; then
        overall_avg=$(echo "scale=2; $total / $count" | bc)
    else
        overall_avg="N/A"
    fi

    json+="
    \"overall_average_ms\": $overall_avg,
    \"tests_passed\": $count,
    \"tests_failed\": $((ITERATIONS - count))
  }
}"

    echo "$json"
}

# 生成并保存报告
report=$(generate_json)
echo "$report" > "$OUTPUT_FILE"

# 打印汇总
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}测试结果汇总${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

for key in "${!results[@]}"; do
    val=${results[$key]}
    # 判断是否达标(<500ms)
    is_fast=$(echo "$val < 500" | bc 2>/dev/null || echo "0")
    if [ "$is_fast" = "1" ] || [ "$val" = "N/A" ]; then
        echo -e "  ${GREEN}✓${NC} $key: ${val}ms"
    else
        echo -e "  ${YELLOW}!${NC} $key: ${val}ms (建议优化)"
    fi
done

echo ""
echo "报告已保存: $OUTPUT_FILE"

# 计算整体平均
total=0
count=0
for val in "${results[@]}"; do
    if [ "$val" != "N/A" ]; then
        total=$(echo "$total + $val" | bc)
        count=$((count + 1))
    fi
done

if [ $count -gt 0 ]; then
    overall=$(echo "scale=2; $total / $count" | bc)
    echo ""
    echo -e "整体平均响应时间: ${overall}ms"
fi

echo ""
echo "测试完成!"
