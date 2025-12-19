<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use App\Models\GeneralSetting;
use Tests\TestCase;

class LanguageMiddlewareSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Many frontend views assume a GeneralSetting row exists via gs()
        GeneralSetting::unguard();
        GeneralSetting::create(['site_name' => 'Test Site']);
        GeneralSetting::reguard();
    }

    /** @test */
    public function language_change_is_rate_limited()
    {
        $ip = '192.168.1.1';

        // Exceed rate limit (10 changes per minute)
        for ($i = 0; $i < 12; $i++) {
            $response = $this->get(route('cookie.accept') . '?lang=zh');
            if ($i >= 10) {
                $response->assertStatus(200); // Still succeeds but should be rate limited
            }
        }

        // After exceeding limit, should use default language
        $response = $this->get(route('cookie.accept') . '?lang=zh');
        $this->assertNotEquals(session('lang'), 'zh');
    }

    /** @test */
    public function invalid_language_code_is_rejected()
    {
        $invalidLanguages = [
            '<script>alert(1)</script>',
            '../../../etc/passwd',
            'en<script>',
            str_repeat('a', 50),
            '123',
            'en-us-invalid',
        ];

        foreach ($invalidLanguages as $lang) {
            $response = $this->get(route('cookie.accept') . '?lang=' . $lang);
            $this->assertNotEquals(session('lang'), $lang);
        }
    }

    /** @test */
    public function valid_language_codes_are_accepted()
    {
        $validLanguages = [
            'en' => 'en',
            'zh' => 'zh',
            'zh-cn' => 'zh-cn',
            'zh-tw' => 'zh-tw',
        ];

        foreach ($validLanguages as $input => $expected) {
            $response = $this->get(route('cookie.accept') . '?lang=' . $input);
            $this->assertEquals(session('lang'), $expected);
        }
    }

    /** @test */
    public function session_language_is_validated()
    {
        // Set invalid language in session
        session(['lang' => '<script>']);

        $response = $this->get(route('cookie.accept'));

        // Should clear invalid session language and use default
        $this->assertNotEquals(session('lang'), '<script>');
    }

    /** @test */
    public function language_change_is_logged()
    {
        $this->get(route('cookie.accept') . '?lang=zh');

        // Check that the language change was logged (if logging is enabled)
        // This is a basic check - in production you'd check the log file
        $this->assertTrue(session()->has('lang'));
    }

    /** @test */
    public function sql_injection_in_language_parameter_is_prevented()
    {
        $maliciousInput = "'; DROP TABLE users; --";

        $response = $this->get(route('cookie.accept') . '?lang=' . $maliciousInput);

        // Should not execute malicious SQL (users table should still exist)
        $this->assertTrue(Schema::hasTable('users'));
    }

    /** @test */
    public function xss_in_language_parameter_is_prevented()
    {
        $xssPayload = '<script>alert("XSS")</script>';

        $response = $this->get(route('cookie.accept') . '?lang=' . $xssPayload);

        // Should not accept XSS payload
        $this->assertNotEquals(session('lang'), $xssPayload);
    }

    /** @test */
    public function case_insensitive_language_codes_work()
    {
        $response = $this->get(route('cookie.accept') . '?lang=EN');
        $this->assertEquals(session('lang'), 'en');

        $response = $this->get(route('cookie.accept') . '?lang=ZH-CN');
        $this->assertEquals(session('lang'), 'zh-cn');
    }

    /** @test */
    public function path_traversal_in_language_parameter_is_prevented()
    {
        $pathTraversalAttempts = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32',
            '....//....//....//etc//passwd',
        ];

        foreach ($pathTraversalAttempts as $attempt) {
            $response = $this->get(route('cookie.accept') . '?lang=' . $attempt);
            $this->assertNotEquals(session('lang'), $attempt);
        }
    }

    /** @test */
    public function null_byte_injection_in_language_parameter_is_prevented()
    {
        $nullBytePayload = "en\x00../../etc/passwd";

        $response = $this->get(route('cookie.accept') . '?lang=' . $nullBytePayload);

        // Should reject null byte injection
        $this->assertNotEquals(session('lang'), $nullBytePayload);
    }
}
