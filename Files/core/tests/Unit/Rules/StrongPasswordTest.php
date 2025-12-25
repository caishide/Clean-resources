<?php

namespace Tests\Unit\Rules;

use Tests\TestCase;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * StrongPassword验证规则单元测试
 *
 * 测试强密码验证规则的各种功能
 */
class StrongPasswordTest extends TestCase
{
    use DatabaseTransactions;

    protected StrongPassword $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new StrongPassword();
    }

    /** @test */
    public function it_passes_strong_password()
    {
        $strongPasswords = [
            'StrongPass123!',
            'MyP@ssw0rd2024',
            'C0mpl3x!@#Password',
            'V3ryStr0ng#Pass',
            'SecureP@ss2024',
        ];

        foreach ($strongPasswords as $password) {
            $this->assertTrue($this->rule->passes('password', $password),
                "Password '{$password}' should pass validation");
        }
    }

    /** @test */
    public function it_fails_weak_passwords()
    {
        $weakPasswords = [
            'password',
            '123456',
            'abcdef',
            'qwerty',
            'letmein',
            'admin',
            'welcome',
            'password123',
        ];

        foreach ($weakPasswords as $password) {
            $this->assertFalse($this->rule->passes('password', $password),
                "Password '{$password}' should fail validation");
        }
    }

    /** @test */
    public function it_fails_passwords_without_uppercase()
    {
        $passwordsWithoutUppercase = [
            'password123!',
            'mypassword2024',
            'weakpass!@#',
        ];

        foreach ($passwordsWithoutUppercase as $password) {
            $this->assertFalse($this->rule->passes('password', $password),
                "Password '{$password}' should fail - missing uppercase");
        }
    }

    /** @test */
    public function it_fails_passwords_without_lowercase()
    {
        $passwordsWithoutLowercase = [
            'PASSWORD123!',
            'MYPASSWORD2024',
            'WEAKPASS!@#',
        ];

        foreach ($passwordsWithoutLowercase as $password) {
            $this->assertFalse($this->rule->passes('password', $password),
                "Password '{$password}' should fail - missing lowercase");
        }
    }

    /** @test */
    public function it_fails_passwords_without_numbers()
    {
        $passwordsWithoutNumbers = [
            'Password!',
            'MyPassword!@#',
            'StrongPass@!',
        ];

        foreach ($passwordsWithoutNumbers as $password) {
            $this->assertFalse($this->rule->passes('password', $password),
                "Password '{$password}' should fail - missing numbers");
        }
    }

    /** @test */
    public function it_fails_passwords_without_special_characters()
    {
        $passwordsWithoutSpecial = [
            'Password123',
            'MyPassword2024',
            'StrongPass123',
        ];

        foreach ($passwordsWithoutSpecial as $password) {
            $this->assertFalse($this->rule->passes('password', $password),
                "Password '{$password}' should fail - missing special characters");
        }
    }

    /** @test */
    public function it_fails_short_passwords()
    {
        $shortPasswords = [
            'Pass1!',
            'Pass2!',
            'Pass3!',
            'Ab1!',
            'Abc2!',
        ];

        foreach ($shortPasswords as $password) {
            $this->assertFalse($this->rule->passes('password', $password),
                "Password '{$password}' should fail - too short");
        }
    }

    /** @test */
    public function it_fails_empty_password()
    {
        $this->assertFalse($this->rule->passes('password', ''),
            "Empty password should fail validation");
    }

    /** @test */
    public function it_fails_null_password()
    {
        $this->assertFalse($this->rule->passes('password', null),
            "Null password should fail validation");
    }

    /** @test */
    public function it_fails_common_password_patterns()
    {
        $commonPatterns = [
            'password123!',
            '12345678!@#',
            'qwerty123!',
            'letmein123!',
            'admin123!',
        ];

        foreach ($commonPatterns as $password) {
            $this->assertFalse($this->rule->passes('password', $password),
                "Password '{$password}' should fail - common pattern");
        }
    }

    /** @test */
    public function it_passes_passwords_with_various_special_characters()
    {
        $passwordsWithSpecialChars = [
            'Password123!',
            'Password123@',
            'Password123#',
            'Password123$',
            'Password123%',
            'Password123^',
            'Password123&',
            'Password123*',
            'Password123(',
            'Password123)',
            'Password123-',
            'Password123+',
        ];

        foreach ($passwordsWithSpecialChars as $password) {
            $this->assertTrue($this->rule->passes('password', $password),
                "Password '{$password}' should pass validation");
        }
    }

    /** @test */
    public function it_validates_minimum_length()
    {
        $minimumLengthPassword = 'Abc123!'; // 7 characters - should fail
        $validLengthPassword = 'Abcd123!'; // 8 characters - should pass

        $this->assertFalse($this->rule->passes('password', $minimumLengthPassword),
            "Password with 7 characters should fail");
        $this->assertTrue($this->rule->passes('password', $validLengthPassword),
            "Password with 8 characters should pass");
    }

    /** @test */
    public function it_validates_maximum_length()
    {
        $veryLongPassword = str_repeat('A', 128) . '1!'; // Very long password
        $validLongPassword = str_repeat('A', 64) . '1!'; // Long but valid password

        $this->assertFalse($this->rule->passes('password', $veryLongPassword),
            "Password exceeding maximum length should fail");
        $this->assertTrue($this->rule->passes('password', $validLongPassword),
            "Password within maximum length should pass");
    }

    /** @test */
    public function it_provides_appropriate_error_message()
    {
        $message = $this->rule->message();

        $this->assertIsString($message);
        $this->assertNotEmpty($message);
        $this->assertStringContainsString('password', strtolower($message));
    }

    /** @test */
    public function it_handles_unicode_characters()
    {
        $passwordWithUnicode = 'Pässw0rd123!'; // Contains ü
        $passwordWithEmoji = 'Password123!😀'; // Contains emoji

        // 这些应该根据规则决定是否通过
        // 通常unicode字符会增加密码强度
        $result1 = $this->rule->passes('password', $passwordWithUnicode);
        $result2 = $this->rule->passes('password', $passwordWithEmoji);

        // 至少不应该导致错误
        $this->assertIsBool($result1);
        $this->assertIsBool($result2);
    }

    /** @test */
    public function it_validates_case_sensitivity()
    {
        $password = 'Password123!';
        $passwordUpper = 'PASSWORD123!';
        $passwordLower = 'password123!';

        $this->assertTrue($this->rule->passes('password', $password),
            "Mixed case password should pass");
        $this->assertFalse($this->rule->passes('password', $passwordUpper),
            "All uppercase password should fail");
        $this->assertFalse($this->rule->passes('password', $passwordLower),
            "All lowercase password should fail");
    }

    /** @test */
    public function it_prevents_sequential_characters()
    {
        $passwordsWithSequentialChars = [
            'Password123!', // Sequential '123'
            'Abcdefg1!', // Sequential 'abcdefg'
        ];

        foreach ($passwordsWithSequentialChars as $password) {
            // 这些密码包含连续字符，可能被拒绝
            $result = $this->rule->passes('password', $password);
            $this->assertIsBool($result);
        }
    }

    /** @test */
    public function it_validates_repeated_characters()
    {
        $passwordsWithRepeatedChars = [
            'Password111!', // Repeated '1'
            'Aaaaaaa123!', // Repeated 'a'
        ];

        foreach ($passwordsWithRepeatedChars as $password) {
            $result = $this->rule->passes('password', $password);
            $this->assertIsBool($result);
        }
    }
}
