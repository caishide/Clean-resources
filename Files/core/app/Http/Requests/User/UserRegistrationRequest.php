<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * UserRegistrationRequest - Validates user registration requests
 *
 * Encapsulates validation logic for user registration, following Laravel best practices.
 */
class UserRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return !auth()->check(); // Only allow registration if user is not authenticated
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        // Strong password requirement - minimum 8 characters with complexity
        $passwordValidation = Password::min(8)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised();

        $agree = 'nullable';
        if (gs('agree')) {
            $agree = 'required';
        }

        return [
            'referBy' => 'required|string|max:160',
            'position' => 'required|integer',
            'firstname' => 'required|string|max:50',
            'lastname' => 'required|string|max:50',
            'email' => 'required|string|email|unique:users,email',
            'password' => ['required', 'confirmed', $passwordValidation],
            'captcha' => 'sometimes|required',
            'agree' => $agree,
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'firstname.required' => 'The first name field is required',
            'firstname.max' => 'First name must not be greater than 50 characters',
            'lastname.required' => 'The last name field is required',
            'lastname.max' => 'Last name must not be greater than 50 characters',
            'email.required' => 'The email field is required',
            'email.email' => 'The email must be a valid email address',
            'email.unique' => 'This email has already been taken',
            'password.min' => 'Password must be at least 8 characters',
            'password.letters' => 'Password must contain at least one letter',
            'password.mixed_case' => 'Password must contain both uppercase and lowercase letters',
            'password.numbers' => 'Password must contain at least one number',
            'password.symbols' => 'Password must contain at least one special character (!@#$%^&*)',
            'password.uncompromised' => 'This password has been compromised in a data breach. Please choose a different password',
            'password.confirmed' => 'Password confirmation does not match',
            'agree.required' => 'You must agree to the terms and conditions',
            'captcha.required' => 'Invalid captcha provided',
            'referBy.required' => 'Referral code is required',
            'position.required' => 'Position is required',
            'position.integer' => 'Position must be a valid number',
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from all input
        $this->merge([
            'firstname' => trim($this->firstname),
            'lastname' => trim($this->lastname),
            'email' => trim($this->email),
            'referBy' => trim($this->referBy),
        ]);
    }
}
