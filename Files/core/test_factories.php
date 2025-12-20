<?php

/*
|--------------------------------------------------------------------------
| Test Factory Classes
|--------------------------------------------------------------------------
|
| 这个脚本用于验证所有工厂类是否能正常工作
|
*/

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Database\Factories\AdminFactory;
use Database\Factories\DepositFactory;
use Database\Factories\WithdrawalFactory;
use Database\Factories\TransactionFactory;
use DatabaseFactories\OrderFactory;
use Database\Factories\BvLogFactory;
use Database\Factories\ProductFactory;
use Database\Factories\CategoryFactory;

echo "测试工厂类...\n";

// 测试AdminFactory
try {
    $admin = AdminFactory::new()->create();
    echo "✓ AdminFactory 工作正常 - ID: {$admin->id}\n";
} catch (Exception $e) {
    echo "✗ AdminFactory 失败: " . $e->getMessage() . "\n";
}

// 测试DepositFactory
try {
    $deposit = DepositFactory::new()->create();
    echo "✓ DepositFactory 工作正常 - ID: {$deposit->id}\n";
} catch (Exception $e) {
    echo "✗ DepositFactory 失败: " . $e->getMessage() . "\n";
}

// 测试WithdrawalFactory
try {
    $withdrawal = WithdrawalFactory::new()->create();
    echo "✓ WithdrawalFactory 工作正常 - ID: {$withdrawal->id}\n";
} catch (Exception $e) {
    echo "✗ WithdrawalFactory 失败: " . $e->getMessage() . "\n";
}

// 测试TransactionFactory
try {
    $transaction = TransactionFactory::new()->create();
    echo "✓ TransactionFactory 工作正常 - ID: {$transaction->id}\n";
} catch (Exception $e) {
    echo "✗ TransactionFactory 失败: " . $e->getMessage() . "\n";
}

// 测试OrderFactory
try {
    $order = OrderFactory::new()->create();
    echo "✓ OrderFactory 工作正常 - ID: {$order->id}\n";
} catch (Exception $e) {
    echo "✗ OrderFactory 失败: " . $e->getMessage() . "\n";
}

// 测试BvLogFactory
try {
    $bvLog = BvLogFactory::new()->create();
    echo "✓ BvLogFactory 工作正常 - ID: {$bvLog->id}\n";
} catch (Exception $e) {
    echo "✗ BvLogFactory 失败: " . $e->getMessage() . "\n";
}

// 测试ProductFactory
try {
    $product = ProductFactory::new()->create();
    echo "✓ ProductFactory 工作正常 - ID: {$product->id}\n";
} catch (Exception $e) {
    echo "✗ ProductFactory 失败: " . $e->getMessage() . "\n";
}

// 测试CategoryFactory
try {
    $category = CategoryFactory::new()->create();
    echo "✓ CategoryFactory 工作正常 - ID: {$category->id}\n";
} catch (Exception $e) {
    echo "✗ CategoryFactory 失败: " . $e->getMessage() . "\n";
}

echo "\n工厂类测试完成!\n";
