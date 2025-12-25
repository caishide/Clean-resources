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
        
        // 确保输入是字符串
        if (!is_string($content)) {
            return '';
        }

        try {
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
        } catch (\Exception $e) {
            // 如果处理失败,返回原始内容的纯文本版本
            $content = strip_tags($content);
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
        // 定义 Word 特殊字符到标准字符的映射 (扩展版本)
        $wordChars = [
            // 智能引号 (Smart Quotes)
            "\xe2\x80\x98" => "'",  // 左单引号
            "\xe2\x80\x99" => "'",  // 右单引号
            "\xe2\x80\x9c" => '"',  // 左双引号
            "\xe2\x80\x9d" => '"',  // 右双引号
            "\xe2\x80\x9b" => "'",  // 单低引号
            "\xe2\x80\x9a" => "'",  // 单高引号
            "\xe2\x80\x9e" => '"',  // 双低引号
            "\xe2\x80\x9f" => '"',  // 双高引号
            
            // 破折号 (Em/En Dashes)
            "\xe2\x80\x93" => '-',  // en dash
            "\xe2\x80\x94" => '--', // em dash
            "\xe2\x80\x95" => '--', // horizontal bar
            "\xe2\x81\x93" => '-',  // small hyphen
            "\xe2\x81\x94" => '-',  // non-breaking hyphen
            "\xe2\x88\x92" => '-',  // minus sign
            
            // 省略号
            "\xe2\x80\xa6" => '...', // 省略号
            "\xe2\x80\xa5" => '.',  // horizontal ellipsis (垂直)
            
            // 其他特殊符号
            "\xe2\x80\xa0" => '+',  // 剑号
            "\xe2\x80\xa1" => '++', // 双剑号
            "\xe2\x80\xb0" => '0/00', // 千分号
            "\xe2\x80\xb9" => '<',  // 单左尖括号
            "\xe2\x80\xba" => '>',  // 单右尖括号
            "\xe2\x80\xa2" => '*',  // bullet
            "\xe2\x80\xa3" => '-',  // triangular bullet
            "\xe2\x81\x83" => '-',  // white bullet
            "\xe2\x80\xa7" => '•',  // bullet separator
            "\xe2\x80\xa8" => '‣',  // triangular bullet
            "\xe2\x80\xa9" => '※',  // reference mark
            "\xe2\x80\xaa" => '‮',  // left-to-right mark
            
            // 商标符号
            "\xc2\xa9" => '(c)',    // 版权符号
            "\xc2\xae" => '(r)',    // 注册商标符号
            "\xe2\x84\xa2" => 'tm', // 商标符号
            "\xe2\x84\xa0" => 'SS', //  servicemark
            
            // 货币符号
            "\xc2\xa3" => 'GBP',    // 英镑符号
            "\xc2\xa5" => 'JPY',    // 日元符号
            "\xe2\x82\xac" => 'EUR', // 欧元符号
            "\xe2\x82\xa3" => '¢',  // 分符号
            "\xe2\x82\xa4" => '₤',  // 英镑符号(旧)
            "\xe2\x82\xa5" => '¥',  // 日元符号
            "\xe2\x82\xa6" => '₦',  // 奈拉符号
            "\xe2\x82\xa7" => '₹',  // 印度卢比符号
            "\xe2\x82\xa8" => '₩',  // 韩元符号
            "\xe2\x82\xa9" => '₪',  // 谢克尔符号
            "\xe2\x82\xaa" => '₫',  // 越南盾符号
            "\xe2\x82\xab" => '€',  // 欧元符号
            "\xe2\x82\xac" => 'EUR', // 欧元符号
            "\xe2\x82\xad" => '₭',  // 老挝基普符号
            "\xe2\x82\xae" => '₮',  // 图格里克符号
            "\xe2\x82\xaf" => '₯',  // 德拉克马符号
            "\xe2\x82\xb0" => '₰',  // 德国马克符号
            "\xe2\x82\xb1" => '₱',  // 菲律宾比索符号
            "\xe2\x82\xb2" => '₲',  // 瓜拉尼符号
            "\xe2\x82\xb3" => '₳',  // 阿根廷比索符号
            "\xe2\x82\xb4" => '₴',  // 格里夫尼亚符号
            "\xe2\x82\xb5" => '₵',  // 塞地符号
            "\xe2\x82\xb6" => '₸',  // 坚戈符号
            "\xe2\x82\xb7" => '₺',  // 土耳其里拉符号
            "\xe2\x82\xb8" => '₼',  // 马纳特符号
            "\xe2\x82\xb9" => '₾',  // 拉里符号
            "\xe2\x82\xba" => '৳',  // 塔卡符号
            "\xe2\x82\xbb" => '₿',  // 比特币符号
            
            // 分数符号
            "\xc2\xbd" => '1/2', // 二分之一
            "\xc2\xbc" => '1/4', // 四分之一
            "\xc2\xbe" => '3/4', // 四分之三
            "\xe2\x85\x90" => '1/7', // 七分之一
            "\xe2\x85\x91" => '1/9', // 九分之一
            "\xe2\x85\x92" => '1/10', // 十分之一
            "\xe2\x85\x93" => '1/3', // 三分之一
            "\xe2\x85\x94" => '2/3', // 三分之二
            "\xe2\x85\x95" => '1/5', // 五分之一
            "\xe2\x85\x96" => '2/5', // 五分之二
            "\xe2\x85\x97" => '3/5', // 五分之三
            "\xe2\x85\x98" => '4/5', // 五分之四
            "\xe2\x85\x99" => '1/6', // 六分之一
            "\xe2\x85\x9a" => '5/6', // 六分之五
            "\xe2\x85\x9b" => '1/8', // 八分之一
            "\xe2\x85\x9c" => '3/8', // 八分之三
            "\xe2\x85\x9d" => '5/8', // 八分之五
            "\xe2\x85\x9e" => '7/8', // 八分之七
            
            // 数学符号
            "\xe2\x80\x89" => ' ', // figure space
            "\xe2\x80\x8a" => ' ', // narrow no-break space
            "\xe2\x80\x8b" => '',  // zero width space (移除)
            "\xe2\x80\x8c" => '',  // zero width non-joiner (移除)
            "\xe2\x80\x8d" => '',  // zero width joiner (移除)
            "\xe2\x80\x8e" => '',  // left-to-right mark (移除)
            "\xe2\x80\x8f" => '',  // right-to-left mark (移除)
            "\xe2\x80\xaf" => ' ', // narrow no-break space
            "\xe2\x81\x9f" => '',  // medium mathematical space (移除)
            
            // 箭头
            "\xe2\x86\x90" => '<-', // 左箭头
            "\xe2\x86\x92" => '->', // 右箭头
            "\xe2\x86\x91" => '^',  // 上箭头
            "\xe2\x86\x93" => 'v',  // 下箭头
            "\xe2\x86\x94" => '<->', // 左右箭头
            "\xe2\x86\x95" => '^v', // 上下箭头
            "\xe2\x87\x90" => '=',  // 等号箭头
            "\xe2\x87\x92" => '=>', // 右双箭头
            "\xe2\x87\x94" => '<=>', // 左右双箭头
            
            // 希腊字母 (常见于 Word 公式)
            "\xce\x91" => 'Alpha',
            "\xce\x92" => 'Beta',
            "\xce\x93" => 'Gamma',
            "\xce\x94" => 'Delta',
            "\xce\x95" => 'Epsilon',
            "\xce\x96" => 'Zeta',
            "\xce\x97" => 'Eta',
            "\xce\x98" => 'Theta',
            "\xce\x99" => 'Iota',
            "\xce\x9a" => 'Kappa',
            "\xce\x9b" => 'Lambda',
            "\xce\x9c" => 'Mu',
            "\xce\x9d" => 'Nu',
            "\xce\x9e" => 'Xi',
            "\xce\x9f" => 'Omicron',
            "\xce\xa0" => 'Pi',
            "\xce\xa1" => 'Rho',
            "\xce\xa3" => 'Sigma',
            "\xce\xa4" => 'Tau',
            "\xce\xa5" => 'Upsilon',
            "\xce\xa6" => 'Phi',
            "\xce\xa7" => 'Chi',
            "\xce\xa8" => 'Psi',
            "\xce\xa9" => 'Omega',
            
            // 大写希腊字母
            "\xce\x91" => 'A',
            "\xce\x92" => 'B',
            "\xce\x93" => 'G',
            "\xce\x94" => 'D',
            "\xce\x95" => 'E',
            "\xce\x96" => 'Z',
            "\xce\x97" => 'H',
            "\xce\x98" => 'Q',
            "\xce\x99" => 'I',
            "\xce\x9a" => 'K',
            "\xce\x9b" => 'L',
            "\xce\x9c" => 'M',
            "\xce\x9d" => 'N',
            "\xce\x9e" => 'X',
            "\xce\x9f" => 'O',
            "\xce\xa0" => 'P',
            "\xce\xa1" => 'R',
            "\xce\xa3" => 'S',
            "\xce\xa4" => 'T',
            "\xce\xa5" => 'Y',
            "\xce\xa6" => 'F',
            "\xce\xa7" => 'C',
            "\xce\xa8" => 'PS',
            "\xce\xa9" => 'W',
        ];

        // 替换所有 Word 特殊字符
        $content = strtr($content, $wordChars);

        // 处理不可见字符和控制字符(保留换行符和制表符)
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content);

        // 处理 Word 特有的 bom 标记
        $content = str_replace("\xef\xbb\xbf", '', $content);

        // 处理 HTML 实体编码的 Word 字符
        $content = preg_replace('/&#[xX]?[0-9a-fA-F]+;/u', '', $content);

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