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
            'placement_id' => 'nullable|integer|min:1',
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
            'firstname.required' => '请输入名字',
            'firstname.max' => '名字不能超过50个字符',
            'lastname.required' => '请输入姓氏',
            'lastname.max' => '姓氏不能超过50个字符',
            'email.required' => '请输入邮箱',
            'email.email' => '邮箱格式不正确',
            'email.unique' => '该邮箱已被注册',
            'password.min' => '密码至少8位',
            'password.letters' => '密码需包含字母',
            'password.mixed_case' => '密码需同时包含大写和小写字母',
            'password.numbers' => '密码需包含数字',
            'password.symbols' => '密码需包含特殊字符',
            'password.uncompromised' => '该密码存在安全风险，请更换',
            'password.confirmed' => '两次输入的密码不一致',
            'agree.required' => '请勾选同意条款',
            'captcha.required' => '验证码无效',
            'referBy.required' => '推荐人必填',
            'position.required' => '请选择安置方向',
            'position.integer' => '安置方向不正确',
            'placement_id.integer' => '安置ID必须是数字',
            'placement_id.min' => '安置ID必须是正整数',
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
