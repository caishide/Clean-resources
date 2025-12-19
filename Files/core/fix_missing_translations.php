<?php
/**
 * 翻译完整性修复脚本
 * 用于自动补充缺失的翻译键
 *
 * 使用方法:
 * php fix_missing_translations.php
 */

echo "=== Laravel 翻译完整性修复脚本 ===\n\n";

// 读取英文翻译文件
$enFile = __DIR__ . '/resources/lang/en.json';
$zhFile = __DIR__ . '/resources/lang/zh.json';

if (!file_exists($enFile)) {
    echo "错误: 找不到英文语言文件: $enFile\n";
    exit(1);
}

if (!file_exists($zhFile)) {
    echo "错误: 找不到中文语言文件: $zhFile\n";
    exit(1);
}

$enTranslations = json_decode(file_get_contents($enFile), true);
$zhTranslations = json_decode(file_get_contents($zhFile), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "错误: JSON 解析失败: " . json_last_error_msg() . "\n";
    exit(1);
}

echo "英文翻译键数量: " . count($enTranslations) . "\n";
echo "中文翻译键数量: " . count($zhTranslations) . "\n\n";

// 查找缺失的翻译键
$missingKeys = array_diff_key($enTranslations, $zhTranslations);

if (empty($missingKeys)) {
    echo "✅ 所有翻译键都已完整!\n";
    exit(0);
}

echo "发现 " . count($missingKeys) . " 个缺失的翻译键:\n";
echo str_repeat('-', 60) . "\n";

$missingCount = 0;
foreach ($missingKeys as $key => $value) {
    echo ($missingCount + 1) . ". $key\n";
    $missingCount++;
}

echo "\n";

// 询问是否自动补充
echo "是否自动补充这些缺失的翻译键? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim(strtolower($line)) !== 'y') {
    echo "操作已取消.\n";
    fclose($handle);
    exit(0);
}
fclose($handle);

echo "\n正在补充翻译键...\n";

// 准备中文翻译映射
$zhMapping = [
    'Total Users' => '总用户数',
    'Active Users' => '活跃用户',
    'Email Unverified Users' => '邮箱未验证用户',
    'Mobile Unverified Users' => '手机未验证用户',
    'Total Invest' => '总投资',
    'Last 7 Days Invest' => '过去7天投资',
    'Total Referral Commission' => '总推荐佣金',
    'Total Binary Commission' => '总二元佣金',
    'Users Total Bv Cut' => '用户总BV扣除',
    'Users Total BV' => '用户总BV',
    'Users Left BV' => '用户左BV',
    'Right BV' => '右BV',
    'Deposited' => '已存款',
    'Withdrawn' => '已提现',
    'Invest' => '投资',
    'Plus Transactions' => '收入交易',
    'Minus Transactions' => '支出交易',
    'Today' => '今天',
    'Yesterday' => '昨天',
    'Last 7 Days' => '过去7天',
    'Last 15 Days' => '过去15天',
    'Last 30 Days' => '过去30天',
    'This Month' => '本月',
    'Last Month' => '上月',
    'Last 6 Months' => '过去6个月',
    'This Year' => '今年',
    'No search result found' => '未找到搜索结果',
    'Couldn\'t upload language image' => '无法上传语言图片',
    'Language added successfully' => '语言添加成功',
    'You\'ve to set another language as default before unset this' => '在取消设置之前，您必须先设置另一种语言作为默认语言',
];

// 备份原文件
$backupFile = $zhFile . '.backup.' . date('Ymd_His');
if (!copy($zhFile, $backupFile)) {
    echo "错误: 无法创建备份文件\n";
    exit(1);
}
echo "✅ 已创建备份文件: " . basename($backupFile) . "\n";

// 添加缺失的翻译
foreach ($missingKeys as $key => $value) {
    $zhValue = isset($zhMapping[$key]) ? $zhMapping[$key] : $key;
    $zhTranslations[$key] = $zhValue;
}

// 保存更新后的文件
$newJson = json_encode($zhTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (file_put_contents($zhFile, $newJson) === false) {
    echo "错误: 无法写入文件\n";
    exit(1);
}

echo "✅ 已成功补充 " . count($missingKeys) . " 个翻译键\n";
echo "✅ 中文语言文件已更新\n\n";

// 验证结果
$updatedZhTranslations = json_decode(file_get_contents($zhFile), true);
echo "更新后中文翻译键数量: " . count($updatedZhTranslations) . "\n";
echo "英文翻译键数量: " . count($enTranslations) . "\n";

if (count($updatedZhTranslations) >= count($enTranslations)) {
    echo "\n✅ 翻译完整性修复完成!\n";
} else {
    echo "\n⚠️  仍有一些翻译键缺失，请手动检查\n";
}

// 统计差异
$stillMissing = array_diff_key($enTranslations, $updatedZhTranslations);
if (!empty($stillMissing)) {
    echo "\n仍缺失的翻译键:\n";
    foreach ($stillMissing as $key => $value) {
        echo "  - $key\n";
    }
}

echo "\n=== 修复完成 ===\n";
