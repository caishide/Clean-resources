#!/bin/bash

echo "=========================================="
echo "BinaryEcom20 全面测试执行脚本"
echo "=========================================="
echo ""

# 切换到项目目录
cd /www/wwwroot/binaryecom20/Files/core

# 检查PHP环境
echo "1. 检查PHP环境..."
php -v
echo ""

# 检查Composer
echo "2. 检查Composer..."
composer --version
echo ""

# 运行单元测试
echo "3. 运行单元测试..."
php vendor/bin/phpunit tests/Unit --testdox --colors=never
echo ""

# 运行功能测试
echo "4. 运行功能测试..."
php vendor/bin/phpunit tests/Feature --testdox --colors=never
echo ""

# 生成覆盖率报告
echo "5. 生成覆盖率报告..."
php vendor/bin/phpunit --coverage-html coverage --coverage-clover coverage/clover.xml --colors=never
echo ""

# 统计测试结果
echo "6. 统计测试结果..."
echo "Unit测试文件数量: $(find tests/Unit -name "*.php" 2>/dev/null | wc -l)"
echo "Feature测试文件数量: $(find tests/Feature -name "*.php" 2>/dev/null | wc -l)"
echo "工厂文件数量: $(find database/factories -name "*.php" 2>/dev/null | wc -l)"
echo ""

echo "=========================================="
echo "测试执行完成"
echo "=========================================="
echo ""
echo "测试报告位置: "
echo "  - HTML报告: coverage/index.html"
echo "  - XML报告: coverage/clover.xml"
echo "  - 测试结果: tests/unit_results.xml, tests/feature_results.xml"
echo ""
