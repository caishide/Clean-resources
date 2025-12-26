#!/bin/bash

# phpMyAdmin 访问修复脚本
# 问题: Nginx 配置的根目录是 /www/server/phpmyadmin,但实际文件在子目录中

echo "正在修复 phpMyAdmin 访问问题..."

# 检查是否以 root 权限运行
if [ "$EUID" -ne 0 ]; then 
    echo "请使用 sudo 运行此脚本"
    echo "命令: sudo bash fix_phpmyadmin.sh"
    exit 1
fi

# 备份当前配置
echo "1. 备份 Nginx 配置..."
cp /www/server/nginx/conf/nginx.conf /www/server/nginx/conf/nginx.conf.backup.$(date +%Y%m%d_%H%M%S)

# 获取 phpMyAdmin 实际目录
PMA_DIR=$(ls -d /www/server/phpmyadmin/phpmyadmin_* 2>/dev/null | head -1)

if [ -z "$PMA_DIR" ]; then
    echo "错误: 找不到 phpMyAdmin 目录"
    exit 1
fi

echo "2. 找到 phpMyAdmin 目录: $PMA_DIR"

# 方案1: 创建符号链接 (推荐)
echo "3. 创建符号链接..."
cd /www/server/phpmyadmin

# 如果已经存在符号链接,先删除
if [ -L "index.php" ]; then
    rm -f index.php
fi

# 创建必要的符号链接
for file in index.php config.inc.php composer.json composer.lock README LICENSE ChangeLog CONTRIBUTING.md; do
    if [ -f "$PMA_DIR/$file" ] && [ ! -e "$file" ]; then
        ln -s "$PMA_DIR/$file" "$file"
        echo "  - 链接: $file"
    fi
done

# 链接所有必要的目录
for dir in js css libraries themes doc examples templates build; do
    if [ -d "$PMA_DIR/$dir" ] && [ ! -e "$dir" ]; then
        ln -s "$PMA_DIR/$dir" "$dir"
        echo "  - 链接: $dir/"
    fi
done

echo "4. 测试 phpMyAdmin 访问..."
curl -I http://localhost:888 2>/dev/null | head -5

echo ""
echo "修复完成!"
echo ""
echo "请尝试访问:"
echo "  http://您的服务器IP:888"
echo "  http://localhost:888"
echo ""
echo "如果仍然无法访问,请检查:"
echo "  1. 防火墙是否开放 888 端口"
echo "  2. 宝塔面板安全组是否允许 888 端口"
echo "  3. Nginx 服务是否正常运行"
echo ""
echo "查看 Nginx 错误日志:"
echo "  tail -f /www/wwwlogs/access.log"
echo ""