#!/bin/bash

# 翻译完整性检查脚本
# 用于定期检查翻译完整性

echo "=== Laravel 翻译完整性检查 ==="
echo ""

# 检查翻译键匹配
echo "1. 检查翻译键匹配..."
echo "----------------------------------------"

EN_KEYS=$(jq -r 'keys[]' resources/lang/en.json | wc -l)
ZH_KEYS=$(jq -r 'keys[]' resources/lang/zh.json | wc -l)

echo "英文翻译键数量: $EN_KEYS"
echo "中文翻译键数量: $ZH_KEYS"

if [ "$EN_KEYS" -eq "$ZH_KEYS" ]; then
    echo "✅ 翻译键数量匹配"
else
    echo "❌ 翻译键数量不匹配"
    echo "缺失的键:"
    jq -r 'keys[]' resources/lang/en.json | while read key; do
        if ! jq -e ".[\"$key\"]" resources/lang/zh.json > /dev/null 2>&1; then
            echo "  - $key"
        fi
    done
fi

echo ""

# 查找硬编码错误消息
echo "2. 检查硬编码错误消息..."
echo "----------------------------------------"

HARDCODED=$(grep -r "withErrors('.*')" app/Http/Controllers --include="*.php" | wc -l)

if [ "$HARDCODED" -eq 0 ]; then
    echo "✅ 未发现硬编码错误消息"
else
    echo "⚠️  发现 $HARDCODED 处硬编码错误消息:"
    grep -r "withErrors('.*')" app/Http/Controllers --include="*.php"
fi

echo ""

# 检查翻译函数使用
echo "3. 检查翻译函数使用..."
echo "----------------------------------------"

LANG_USAGE=$(grep -r "@lang\|__(" resources/views --include="*.blade.php" | wc -l)
echo "翻译函数使用次数: $LANG_USAGE"

echo ""

# 检查管理员翻译
echo "4. 检查管理员翻译..."
echo "----------------------------------------"

ADMIN_EN=$(jq -r 'keys[]' resources/lang/en/admin.php | wc -l)
ADMIN_ZH=$(jq -r 'keys[]' resources/lang/zh/admin.php | wc -l)

echo "管理员英文翻译键数量: $ADMIN_EN"
echo "管理员中文翻译键数量: $ADMIN_ZH"

if [ "$ADMIN_EN" -eq "$ADMIN_ZH" ]; then
    echo "✅ 管理员翻译匹配"
else
    echo "❌ 管理员翻译不匹配"
fi

echo ""
echo "=== 检查完成 ==="
