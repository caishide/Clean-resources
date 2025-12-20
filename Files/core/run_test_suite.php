<?php

/**
 * BinaryEcom20 测试套件运行脚本
 * 包含单元测试、功能测试、覆盖率分析
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "==========================================\n";
echo "BinaryEcom20 全面测试套件\n";
echo "==========================================\n\n";

echo "1. 检查PHP环境...\n";
echo "PHP版本: " . PHP_VERSION . "\n";
echo "内存限制: " . ini_get('memory_limit') . "\n\n";

// 检查composer依赖
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo "错误: vendor/autoload.php 不存在。请运行 'composer install'.\n";
    exit(1);
}

require __DIR__ . '/vendor/autoload.php';

echo "2. 检查测试配置...\n";
if (!file_exists(__DIR__ . '/phpunit.xml')) {
    echo "错误: phpunit.xml 不存在.\n";
    exit(1);
}
echo "phpunit.xml: 存在\n\n";

echo "3. 运行测试套件...\n\n";

// 运行PHPUnit测试
$phpunit = __DIR__ . '/vendor/bin/phpunit';

// 基本测试运行
echo "----------------------------------------\n";
echo "运行单元测试和功能测试\n";
echo "----------------------------------------\n";

$command = "cd /www/wwwroot/binaryecom20/Files/core && php vendor/bin/phpunit --testdox --colors=never";
$output = shell_exec($command . ' 2>&1');

echo $output . "\n\n";

// 运行覆盖率测试
echo "----------------------------------------\n";
echo "生成代码覆盖率报告\n";
echo "----------------------------------------\n";

$coverageCommand = "cd /www/wwwroot/binaryecom20/Files/core && php vendor/bin/phpunit --coverage-html coverage --coverage-clover coverage/clover.xml --colors=never";
$coverageOutput = shell_exec($coverageCommand . ' 2>&1');

echo $coverageOutput . "\n\n";

// 分析测试文件
echo "----------------------------------------\n";
echo "分析现有测试文件\n";
echo "----------------------------------------\n";

function scanTestFiles($dir, $prefix = '') {
    $files = [];
    if (!is_dir($dir)) return $files;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $files = array_merge($files, scanTestFiles($path, $prefix . $item . '/'));
        } elseif (substr($item, -4) === '.php') {
            $files[] = $prefix . $item;
        }
    }
    return $files;
}

$testFiles = scanTestFiles(__DIR__ . '/tests');
echo "发现的测试文件:\n";
foreach ($testFiles as $file) {
    echo "  - $file\n";
}
echo "总计: " . count($testFiles) . " 个测试文件\n\n";

// 检查模型文件
echo "----------------------------------------\n";
echo "分析模型文件\n";
echo "----------------------------------------\n";

$modelFiles = scanTestFiles(__DIR__ . '/app/Models');
echo "发现的模型文件:\n";
foreach ($modelFiles as $file) {
    echo "  - $file\n";
}
echo "总计: " . count($modelFiles) . " 个模型文件\n\n";

// 检查工厂文件
echo "----------------------------------------\n";
echo "分析工厂文件\n";
echo "----------------------------------------\n";

$factoryFiles = scanTestFiles(__DIR__ . '/database/factories');
echo "发现的工厂文件:\n";
foreach ($factoryFiles as $file) {
    echo "  - $file\n";
}
echo "总计: " . count($factoryFiles) . " 个工厂文件\n\n";

echo "==========================================\n";
echo "测试套件执行完成\n";
echo "==========================================\n";
