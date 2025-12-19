<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

/**
 * StrongPassword - Custom validation rule for strong passwords
 *
 * Enforces password complexity requirements:
 * - Minimum 8 characters
 * - At least one uppercase letter
 * - At least one lowercase letter
 * - At least one digit
 * - At least one special character
 * - Not compromised in data breaches
 */
class StrongPassword implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value)) {
            $fail('The :attribute must be a string.');
            return;
        }

        // Check minimum length
        if (strlen($value) < 8) {
            $fail('The :attribute must be at least 8 characters.');
            return;
        }

        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');
            return;
        }

        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');
            return;
        }

        // Check for digit
        if (!preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');
            return;
        }

        // Check for special character
        if (!preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('The :attribute must contain at least one special character.');
            return;
        }

        // Check if password is compromised
        if (method_exists(Password::class, 'uncompromised')) {
            $validator = Validator::make(['password' => $value], [
                'password' => 'uncompromised',
            ]);

            if ($validator->fails()) {
                $fail('This password has been compromised in a data breach. Please choose a different password.');
                return;
            }
        }

        // Check for common patterns
        $commonPatterns = [
            '123456',
            'password',
            'qwerty',
            'admin',
            'letmein',
            'welcome',
            'monkey',
        ];

        foreach ($commonPatterns as $pattern) {
            if (stripos($value, $pattern) !== false) {
                $fail('The :attribute contains common patterns and is not secure.');
                return;
            }
        }
    }
}
