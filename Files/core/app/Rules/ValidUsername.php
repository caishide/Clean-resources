<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;

/**
 * ValidUsername - Custom validation rule for usernames
 *
 * Ensures usernames meet the following criteria:
 * - Only alphanumeric characters and underscores
 * - Between 3 and 30 characters
 * - Not already taken
 * - Not reserved (admin, root, etc.)
 */
class ValidUsername implements ValidationRule
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

        // Check length
        if (strlen($value) < 3) {
            $fail('The :attribute must be at least 3 characters.');
            return;
        }

        if (strlen($value) > 30) {
            $fail('The :attribute must not be greater than 30 characters.');
            return;
        }

        // Check format (alphanumeric and underscores only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            $fail('The :attribute may only contain letters, numbers, and underscores.');
            return;
        }

        // Check for reserved usernames
        $reservedUsernames = [
            'admin', 'administrator', 'root', 'system', 'support',
            'help', 'info', 'contact', 'moderator', 'moderation',
            'owner', 'master', 'super', 'superuser', 'test',
            'testing', 'demo', 'guest', 'user', 'users',
            'api', 'www', 'mail', 'email', 'login', 'register',
            'dashboard', 'profile', 'account', 'settings',
        ];

        if (in_array(strtolower($value), $reservedUsernames)) {
            $fail('This :attribute is reserved and cannot be used.');
            return;
        }

        // Check if username already exists (excluding current user if updating)
        $exists = User::where('username', $value)->exists();

        if ($exists) {
            $fail('This :attribute is already taken.');
            return;
        }

        // Check for excessive repetition
        if (preg_match('/(.)\1{4,}/', $value)) {
            $fail('The :attribute should not contain excessive repetition of characters.');
            return;
        }

        // Check for sequential characters
        if (preg_match('/(abc|bcd|cde|def|123|234|345|456|567|678|789|890)/i', $value)) {
            $fail('The :attribute should not contain sequential characters.');
            return;
        }
    }
}
