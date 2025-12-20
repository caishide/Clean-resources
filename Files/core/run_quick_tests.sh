#!/bin/bash

echo "=========================================="
echo "BinaryEcom20 快速测试执行"
echo "=========================================="
echo ""

# 切换到项目目录
cd /www/wwwroot/binaryecom20/Files/core

echo "1. 运行单元测试..."
php vendor/bin/phpunit tests/Unit --testdox
echo ""

echo "2. 运行功能测试..."
php vendor/bin/phpunit tests/Feature --testdox
echo ""

echo "3. 生成简单覆盖率报告..."
php vendor/bin/phpunit --coverage-text --colors=never
echo ""

echo "=========================================="
echo "测试执行完成"
echo "=========================================="
echo ""

# 统计结果
echo "测试统计:"
echo "  - Unit测试文件: $(find tests/Unit -name "*.php" 2>/dev/null | wc -l)"
echo "  - Feature测试文件: $(find tests/Feature -name "*.php" 2>/dev/null | wc -l)"
echo "  - 工厂文件: $(find database/factories -name "*.php" 2>/dev/null | wc -l)"
echo ""

echo "测试报告文件:"
echo "  - 测试总结: TESTING_SUMMARY.md"
echo "  - 覆盖率报告: coverage/index.html (运行完整测试后生成)"
echo ""
