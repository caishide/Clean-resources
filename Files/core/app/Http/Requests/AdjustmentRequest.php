<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * 调整请求验证类
 * 
 * 验证退款和调整相关的输入数据
 */
class AdjustmentRequest extends FormRequest
{
    /**
     * 确定用户是否有权限进行此请求
     */
    public function authorize(): bool
    {
        // 只允许有创建调整权限的用户
        return $this->user() && $this->user()->can('create adjustments');
    }

    /**
     * 获取验证规则
     */
    public function rules(): array
    {
        return [
            'order_id' => 'required|integer|exists:orders,id',
            'reason' => 'required|string|min:10|max:500',
            'reason_type' => 'required|in:refund_before_finalize,refund_after_finalize,manual_adjustment',
            'admin_notes' => 'nullable|string|max:1000',
            'amount' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * 获取自定义验证错误消息
     */
    public function messages(): array
    {
        return [
            'order_id.required' => '订单ID不能为空',
            'order_id.integer' => '订单ID必须是整数',
            'order_id.exists' => '订单不存在',
            'reason.required' => '调整原因不能为空',
            'reason.min' => '调整原因至少需要10个字符',
            'reason.max' => '调整原因不能超过500个字符',
            'reason_type.required' => '调整类型不能为空',
            'reason_type.in' => '调整类型无效',
            'admin_notes.max' => '管理员备注不能超过1000个字符',
            'amount.numeric' => '金额必须是数字',
            'amount.min' => '金额不能为负数',
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
     * 准备验证数据
     */
    protected function prepareForValidation()
    {
        // 清理输入数据
        $this->merge([
            'reason' => trim(strip_tags($this->reason)),
            'admin_notes' => $this->admin_notes ? trim(strip_tags($this->admin_notes)) : null,
        ]);
    }
}