<?php
/**
 * 硬编码文本修复脚本
 * 自动修复控制器和视图中的硬编码消息
 *
 * 使用方法:
 * php fix_hardcoded_messages.php
 */

echo "=== 硬编码文本修复脚本 ===\n\n";

// 定义需要替换的硬编码消息
$replacements = [
    // 控制器中的错误消息
    'app/Http/Controllers/Admin/AdminController.php' => [
        "'Something went wrong'" => "__('admin.error.something_wrong')",
        '"Something went wrong"' => '__("admin.error.something_wrong")',
    ],
];

// 检查是否在正确的目录
if (!file_exists(__DIR__ . '/app/Http/Controllers/Admin/AdminController.php')) {
    echo "错误: 请在 Laravel 项目根目录下运行此脚本\n";
    exit(1);
}

echo "发现需要修复的文件:\n";
echo str_repeat('-', 60) . "\n";

foreach ($replacements as $file => $replacementList) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "✅ $file\n";
    } else {
        echo "⚠️  $file (文件不存在)\n";
    }
}

echo "\n";

// 询问是否继续
echo "是否开始修复? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim(strtolower($line)) !== 'y') {
    echo "操作已取消.\n";
    fclose($handle);
    exit(0);
}
fclose($handle);

echo "\n正在修复硬编码文本...\n\n";

$fixedCount = 0;

// 修复每个文件
foreach ($replacements as $file => $replacementList) {
    $filePath = __DIR__ . '/' . $file;

    if (!file_exists($filePath)) {
        echo "⚠️  跳过不存在的文件: $file\n";
        continue;
    }

    // 备份原文件
    $backupFile = $filePath . '.backup.' . date('Ymd_His');
    if (!copy($filePath, $backupFile)) {
        echo "错误: 无法创建备份文件: $file\n";
        continue;
    }

    // 读取文件内容
    $content = file_get_contents($filePath);

    // 执行替换
    $modified = false;
    foreach ($replacementList as $search => $replace) {
        if (strpos($content, $search) !== false) {
            $content = str_replace($search, $replace, $content);
            $modified = true;
            $fixedCount++;
            echo "✅ 已替换: $search -> $replace\n";
        }
    }

    // 保存修改后的文件
    if ($modified) {
        if (file_put_contents($filePath, $content) === false) {
            echo "❌ 写入文件失败: $file\n";
            // 恢复备份
            copy($backupFile, $filePath);
            continue;
        }
        echo "✅ 已更新: $file\n";
    } else {
        echo "ℹ️  无需修改: $file\n";
        // 删除未使用的备份
        unlink($backupFile);
    }
}

echo "\n=== 修复完成 ===\n";
echo "总计修复: $fixedCount 处硬编码\n\n";

// 提供后续建议
echo "⚠️  重要提醒:\n";
echo "1. 请在语言文件中添加以下翻译键:\n";
echo "   admin.error.something_wrong = '出现错误'\n\n";

echo "2. 手动检查和修复以下文件中的硬编码:\n";
echo "   - admin/setting/bonus_config.blade.php (中文硬编码)\n";
echo "   - admin/gateways/automatic/edit.blade.php (JS alert)\n\n";

echo "3. 运行以下命令验证修复:\n";
echo "   php artisan view:clear\n";
echo "   php artisan config:clear\n\n";

echo "=== 脚本结束 ===\n";
