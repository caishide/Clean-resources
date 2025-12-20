<?php

/**
 * Binary Ecom 工厂类快速验证脚本
 *
 * 此脚本用于快速验证所有工厂类是否已正确创建并可以使用
 */

echo "================================\n";
echo "Binary Ecom 工厂类验证脚本\n";
echo "================================\n\n";

// 定义需要检查的工厂文件
$requiredFactories = [
    'AdminFactory.php',
    'DepositFactory.php',
    'WithdrawalFactory.php',
    'TransactionFactory.php',
    'OrderFactory.php',
    'BvLogFactory.php',
    'ProductFactory.php',
    'CategoryFactory.php',
];

$existingFactories = [
    'UserFactory.php',
    'UserExtraFactory.php',
    'WithdrawMethodFactory.php',
];

$factoryDir = __DIR__ . '/database/factories';

echo "检查必需的工厂类...\n";
echo "----------------------------------------\n";

foreach ($requiredFactories as $factory) {
    $filePath = $factoryDir . '/' . $factory;
    if (file_exists($filePath)) {
        echo "✅ $factory\n";
    } else {
        echo "❌ $factory (缺失)\n";
    }
}

echo "\n检查已存在的工厂类...\n";
echo "----------------------------------------\n";

foreach ($existingFactories as $factory) {
    $filePath = $factoryDir . '/' . $factory;
    if (file_exists($filePath)) {
        echo "✅ $factory\n";
    } else {
        echo "❌ $factory (缺失)\n";
    }
}

echo "\n检查模型文件的HasFactory特征...\n";
echo "----------------------------------------\n";

$models = [
    __DIR__ . '/app/Models/Admin.php',
    __DIR__ . '/app/Models/Product.php',
    __DIR__ . '/app/Models/Category.php',
];

foreach ($models as $model) {
    $modelName = basename($model);
    if (file_exists($model)) {
        $content = file_get_contents($model);
        if (strpos($content, 'use HasFactory') !== false) {
            echo "✅ $modelName - HasFactory已添加\n";
        } else {
            echo "❌ $modelName - HasFactory缺失\n";
        }
    } else {
        echo "❌ $modelName - 文件不存在\n";
    }
}

echo "\n================================\n";
echo "验证完成!\n";
echo "================================\n";
echo "\n下一步：\n";
echo "1. 运行 composer dump-autoload 重新加载自动加载\n";
echo "2. 运行 php artisan test --filter=AdminControllerTest 测试控制器\n";
echo "3. 检查测试结果是否通过\n";
