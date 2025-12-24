<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 结算请求验证类
 * 
 * 验证周结算和季度结算的输入数据
 */
class SettlementRequest extends FormRequest
{
    /**
     * 确定用户是否有权限进行此请求
     */
    public function authorize(): bool
    {
        // 只允许有执行结算权限的用户
        return $this->user() && $this->user()->can('execute weekly settlement');
    }

    /**
     * 获取验证规则
     */
    public function rules(): array
    {
        $rules = [
            'week_key' => 'required|string|regex:/^\d{4}-W\d{2}$/',
            'dry_run' => 'nullable|boolean',
            'ignore_lock' => 'nullable|boolean',
        ];

        // 如果是季度结算
        if ($this->has('quarter_key')) {
            $rules = [
                'quarter_key' => 'required|string|regex:/^\d{4}-Q[1-4]$/',
                'dry_run' => 'nullable|boolean',
            ];
        }

        return $rules;
    }

    /**
     * 获取自定义验证错误消息
     */
    public function messages(): array
    {
        return [
            'week_key.required' => '周键不能为空',
            'week_key.regex' => '周键格式无效，正确格式为：2025-W51',
            'quarter_key.required' => '季度键不能为空',
            'quarter_key.regex' => '季度键格式无效，正确格式为：2025-Q1',
            'dry_run.boolean' => '预演模式必须是布尔值',
            'ignore_lock.boolean' => '忽略锁必须是布尔值',
        ];
    }

    /**
     * 验证失败时的处理
     */
    protected function failedValidation(Validator $validator)
    {
        $response = response()->json([
            'status' => 'error',
            'message' => '验证失败',
            'errors' => $validator->errors()
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * 获取验证后的数据
     */
    public function validated(): array
    {
        $data = parent::validated();
        
        // 设置默认值
        $data['dry_run'] = $this->has('dry_run') ? (bool) $this->dry_run : false;
        $data['ignore_lock'] = $this->has('ignore_lock') ? (bool) $this->ignore_lock : false;
        
        return $data;
    }
}