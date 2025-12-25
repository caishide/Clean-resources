<?php

namespace App\Helpers;

/**
 * ContentSanitizer - 清理和标准化用户输入的内容
 * 
 * 主要用于处理从 Word 文档等富文本编辑器复制的内容,
 * 移除或转换可能导致问题的特殊字符和格式。
 */
class ContentSanitizer
{
    /**
     * 清理 HTML 内容,移除 Word 文档的特殊格式和字符
     *
     * @param string|null $content 原始内容
     * @param bool $stripTags 是否移除所有 HTML 标签
     * @return string 清理后的内容
     */
    public static function sanitize(?string $content, bool $stripTags = false): string
    {
        if (empty($content)) {
            return '';
        }

        // 如果需要完全移除 HTML 标签
        if ($stripTags) {
            $content = strip_tags($content);
        }

        // 替换 Word 文档的特殊字符为标准 ASCII 字符
        $content = self::normalizeWordCharacters($content);

        // 移除多余的空白字符
        $content = self::normalizeWhitespace($content);

        // 移除危险的 HTML 标签和属性(如果保留 HTML)
        if (!$stripTags) {
            $content = self::sanitizeHtml($content);
        }

        return trim($content);
    }

    /**
     * 标准化 Word 文档的特殊字符
     *
     * Word 文档使用特殊的 Unicode 字符代替标准的标点符号,
     * 这些字符在某些情况下可能导致显示或存储问题。
     *
     * @param string $content 原始内容
     * @return string 标准化后的内容
     */
    private static function normalizeWordCharacters(string $content): string
    {
        // 定义 Word 特殊字符到标准字符的映射
        $wordChars = [
            // 智能引号 (Smart Quotes)
            "\xe2\x80\x98" => "'",  // 左单引号
            "\xe2\x80\x99" => "'",  // 右单引号
            "\xe2\x80\x9c" => '"',  // 左双引号
            "\xe2\x80\x9d" => '"',  // 右双引号
            "\xe2\x80\x9b" => "'",  // 单低引号
            
            // 破折号 (Em/En Dashes)
            "\xe2\x80\x93" => '-',  // en dash
            "\xe2\x80\x94" => '--', // em dash
            "\xe2\x80\x95" => '--', // horizontal bar
            
            // 省略号
            "\xe2\x80\xa6" => '...', // 省略号
            
            // 其他特殊符号
            "\xe2\x80\xa0" => '+',  // 剑号
            "\xe2\x80\xa1" => '++', // 双剑号
            "\xe2\x80\xb0" => '0/00', // 千分号
            "\xe2\x80\xb9" => '<',  // 单左尖括号
            "\xe2\x80\xba" => '>',  // 单右尖括号
            "\xe2\x80\x93" => '-',  // en dash (重复)
            "\xe2\x80\x94" => '--', // em dash (重复)
            
            // 商标符号
            "\xc2\xa9" => '(c)',    // 版权符号
            "\xc2\xae" => '(r)',    // 注册商标符号
            "\xe2\x84\xa2" => 'tm', // 商标符号
            
            // 货币符号
            "\xc2\xa3" => 'GBP',    // 英镑符号
            "\xc2\xa5" => 'JPY',    // 日元符号
            "\xe2\x82\xac" => 'EUR', // 欧元符号
            
            // 项目符号
            "\xe2\x80\xa2" => '*',  // 项目符号
            "\xe2\x80\xa3" => '-',  // 三角项目符号
            "\xe2\x81\x83" => '-',  // 三角项目符号
            
            // 箭头
            "\xe2\x86\x90" => '<-', // 左箭头
            "\xe2\x86\x92" => '->', // 右箭头
            "\xe2\x86\x91" => '^',  // 上箭头
            "\xe2\x86\x93" => 'v',  // 下箭头
        ];

        // 替换所有 Word 特殊字符
        $content = strtr($content, $wordChars);

        // 处理不可见字符和控制字符(保留换行符和制表符)
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);

        return $content;
    }

    /**
     * 标准化空白字符
     *
     * @param string $content 原始内容
     * @return string 标准化后的内容
     */
    private static function normalizeWhitespace(string $content): string
    {
        // 将多个连续空格替换为单个空格
        $content = preg_replace('/[ \t]+/', ' ', $content);
        
        // 将多个连续换行符替换为最多两个换行符
        $content = preg_replace('/\r\n|\r|\n/', "\n", $content); // 统一换行符
        $content = preg_replace('/\n{3,}/', "\n\n", $content); // 最多两个连续换行
        
        // 移除行首行尾的空格
        $lines = explode("\n", $content);
        $lines = array_map('trim', $lines);
        $content = implode("\n", $lines);

        return $content;
    }

    /**
     * 清理 HTML 内容,移除危险的标签和属性
     *
     * @param string $content HTML 内容
     * @return string 清理后的 HTML 内容
     */
    private static function sanitizeHtml(string $content): string
    {
        // 移除危险的 HTML 标签
        $dangerousTags = [
            'script', 'style', 'iframe', 'form', 'input', 'button',
            'object', 'embed', 'link', 'meta', 'applet'
        ];
        
        foreach ($dangerousTags as $tag) {
            $content = preg_replace('#<' . $tag . '.*?>.*?</' . $tag . '>#is', '', $content);
            $content = preg_replace('#<' . $tag . '.*?/>#is', '', $content);
        }

        // 移除事件处理器属性 (如 onclick, onerror 等)
        $content = preg_replace('#\s*on\w+\s*=\s*["\'][^"\']*["\']#is', '', $content);
        
        // 移除 javascript: 协议
        $content = preg_replace('#\s*href\s*=\s*["\']javascript:[^"\']*["\']#is', '', $content);

        return $content;
    }

    /**
     * 截断内容到指定长度,保留完整的单词
     *
     * @param string $content 原始内容
     * @param int $maxLength 最大长度
     * @param string $suffix 后缀(如 "...")
     * @return string 截断后的内容
     */
    public static function truncate(string $content, int $maxLength = 255, string $suffix = '...'): string
    {
        if (mb_strlen($content) <= $maxLength) {
            return $content;
        }

        $truncated = mb_substr($content, 0, $maxLength - mb_strlen($suffix));
        
        // 确保在单词边界处截断
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }

        return $truncated . $suffix;
    }

    /**
     * 生成 URL 友好的 slug
     *
     * @param string $text 原始文本
     * @return string URL slug
     */
    public static function slugify(string $text): string
    {
        // 替换非字母数字字符为连字符
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        
        // 转换为小写
        $text = mb_strtolower($text);
        
        // 移除不需要的字符
        $text = preg_replace('~[^-\w]+~', '', $text);
        
        // 移除多余的连字符
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        
        return $text;
    }
}