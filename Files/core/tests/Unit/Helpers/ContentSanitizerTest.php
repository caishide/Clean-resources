<?php

namespace Tests\Unit\Helpers;

use App\Helpers\ContentSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * ContentSanitizerTest - 测试内容清理功能
 * 
 * 验证从 Word 文档复制的内容能够正确清理和标准化
 */
class ContentSanitizerTest extends TestCase
{
    /**
     * 测试清理 Word 智能引号
     */
    public function test_normalize_smart_quotes(): void
    {
        // 使用 Word 的智能引号字符
        $content = "This is \xe2\x80\x9cquoted\xe2\x80\x9d text with \xe2\x80\x98single\xe2\x80\x99 quotes";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringContainsString('"quoted"', $cleaned);
        $this->assertStringContainsString("'single'", $cleaned);
        $this->assertStringNotContainsString("\xe2\x80\x9c", $cleaned); // 左双引号
        $this->assertStringNotContainsString("\xe2\x80\x9d", $cleaned); // 右双引号
    }

    /**
     * 测试清理 Word 破折号
     */
    public function test_normalize_dashes(): void
    {
        $content = "Text with em dash — and en dash –";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringContainsString('--', $cleaned);
        $this->assertStringContainsString('-', $cleaned);
        $this->assertStringNotContainsString("\xe2\x80\x94", $cleaned); // em dash
        $this->assertStringNotContainsString("\xe2\x80\x93", $cleaned); // en dash
    }

    /**
     * 测试清理省略号
     */
    public function test_normalize_ellipsis(): void
    {
        $content = "Text with ellipsis…";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringContainsString('...', $cleaned);
        $this->assertStringNotContainsString("\xe2\x80\xa6", $cleaned); // 省略号
    }

    /**
     * 测试清理商标符号
     */
    public function test_normalize_trademark_symbols(): void
    {
        $content = "Copyright © 2024, Registered ®, Trademark ™";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringContainsString('(c)', $cleaned);
        $this->assertStringContainsString('(r)', $cleaned);
        $this->assertStringContainsString('tm', $cleaned);
    }

    /**
     * 测试清理货币符号
     */
    public function test_normalize_currency_symbols(): void
    {
        $content = "Price: £100, ¥1000, €200";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringContainsString('GBP', $cleaned);
        $this->assertStringContainsString('JPY', $cleaned);
        $this->assertStringContainsString('EUR', $cleaned);
    }

    /**
     * 测试清理项目符号
     */
    public function test_normalize_bullets(): void
    {
        $content = "• Item 1\n• Item 2\n• Item 3";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringContainsString('*', $cleaned);
        $this->assertStringNotContainsString("\xe2\x80\xa2", $cleaned); // 项目符号
    }

    /**
     * 测试标准化空白字符
     */
    public function test_normalize_whitespace(): void
    {
        $content = "Text    with    multiple    spaces\n\n\n\nand\n\n\nnewlines";
        $cleaned = ContentSanitizer::sanitize($content);
        
        // 多个空格应该被替换为单个空格
        $this->assertStringNotContainsString('    ', $cleaned);
        // 多个换行符应该被替换为最多两个
        $this->assertStringNotContainsString("\n\n\n", $cleaned);
    }

    /**
     * 测试移除危险的 HTML 标签
     */
    public function test_remove_dangerous_html(): void
    {
        $content = "<p>Safe content</p><script>alert('xss')</script><div>More content</div>";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringContainsString('<p>Safe content</p>', $cleaned);
        $this->assertStringContainsString('<div>More content</div>', $cleaned);
        $this->assertStringNotContainsString('<script>', $cleaned);
        $this->assertStringNotContainsString('alert', $cleaned);
    }

    /**
     * 测试移除事件处理器属性
     */
    public function test_remove_event_handlers(): void
    {
        $content = '<div onclick="alert(1)" onmouseover="alert(2)">Content</div>';
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringNotContainsString('onmouseover', $cleaned);
        $this->assertStringContainsString('<div>Content</div>', $cleaned);
    }

    /**
     * 测试移除 javascript: 协议
     */
    public function test_remove_javascript_protocol(): void
    {
        $content = '<a href="javascript:alert(1)">Link</a>';
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringNotContainsString('javascript:', $cleaned);
    }

    /**
     * 测试完全移除 HTML 标签
     */
    public function test_strip_html_tags(): void
    {
        $content = "<p>Paragraph</p><div>Division</div>";
        $cleaned = ContentSanitizer::sanitize($content, stripTags: true);
        
        $this->assertStringNotContainsString('<p>', $cleaned);
        $this->assertStringNotContainsString('<div>', $cleaned);
        $this->assertStringContainsString('Paragraph', $cleaned);
        $this->assertStringContainsString('Division', $cleaned);
    }

    /**
     * 测试截断内容
     */
    public function test_truncate_content(): void
    {
        $content = "This is a very long content that needs to be truncated";
        $truncated = ContentSanitizer::truncate($content, 20);
        
        $this->assertLessThanOrEqual(23, mb_strlen($truncated)); // 20 + '...'
        $this->assertStringEndsWith('...', $truncated);
    }

    /**
     * 测试在单词边界处截断
     */
    public function test_truncate_at_word_boundary(): void
    {
        $content = "This is a very long content";
        $truncated = ContentSanitizer::truncate($content, 15);
        
        // 应该在单词边界处截断,而不是在单词中间
        $this->assertStringNotContainsString('very lo', $truncated);
        $this->assertStringContainsString('...', $truncated);
    }

    /**
     * 测试生成 URL slug
     */
    public function test_slugify_text(): void
    {
        $text = "Hello World! This is a Test";
        $slug = ContentSanitizer::slugify($text);
        
        $this->assertEquals('hello-world-this-is-a-test', $slug);
    }

    /**
     * 测试 slugify 处理特殊字符
     */
    public function test_slugify_special_characters(): void
    {
        $text = "Café & Restaurant™";
        $slug = ContentSanitizer::slugify($text);
        
        $this->assertStringNotContainsString('™', $slug);
        $this->assertStringContainsString('cafe', $slug);
    }

    /**
     * 测试处理空内容
     */
    public function test_handle_empty_content(): void
    {
        $cleaned = ContentSanitizer::sanitize('');
        $this->assertEquals('', $cleaned);
        
        $cleaned = ContentSanitizer::sanitize(null);
        $this->assertEquals('', $cleaned);
    }

    /**
     * 测试处理纯文本内容
     */
    public function test_handle_plain_text(): void
    {
        $content = "This is plain text without any special characters";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertEquals($content, $cleaned);
    }

    /**
     * 测试处理混合内容(Word 文档 + HTML)
     */
    public function test_handle_mixed_content(): void
    {
        // 使用 Word 的智能引号和破折号字符
        $content = "<p>Text with \xe2\x80\x9csmart quotes\xe2\x80\x9d and \xe2\x80\x94 em dash</p>";
        $cleaned = ContentSanitizer::sanitize($content);
        
        $this->assertStringContainsString('<p>', $cleaned);
        $this->assertStringContainsString('"smart quotes"', $cleaned);
        $this->assertStringContainsString('--', $cleaned);
        $this->assertStringNotContainsString("\xe2\x80\x9c", $cleaned);
    }
}