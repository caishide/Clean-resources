#!/bin/bash

# BinaryEcom20 测试执行脚本

cd /www/wwwroot/binaryecom20/Files/core

echo "=========================================="
echo "BinaryEcom20 全面测试套件执行"
echo "=========================================="
echo ""

# 1. 环境检查
echo "1. 环境检查..."
echo "PHP版本: $(php -v | head -n1)"
echo "Composer版本: $(composer --version 2>/dev/null || echo '未安装')"
echo "内存限制: $(php -i | grep memory_limit | head -n1)"
echo ""

# 2. 运行基本测试
echo "2. 运行基本测试..."
php vendor/bin/phpunit --testdox --colors=never
echo ""

# 3. 运行覆盖率测试
echo "3. 生成代码覆盖率报告..."
php vendor/bin/phpunit --coverage-html coverage --coverage-clover coverage/clover.xml --colors=never
echo ""

# 4. 统计测试文件
echo "4. 测试文件统计..."
echo "Unit测试文件数量: $(find tests/Unit -name "*.php" 2>/dev/null | wc -l)"
echo "Feature测试文件数量: $(find tests/Feature -name "*.php" 2>/dev/null | wc -l)"
echo "模型工厂文件数量: $(find database/factories -name "*.php" 2>/dev/null | wc -l)"
echo ""

echo "=========================================="
echo "测试执行完成"
echo "=========================================="
