<?php

namespace Tests\Unit\Requests;

use Tests\TestCase;
use App\Http\Requests\AdjustmentRequest;
use App\Http\Requests\SettlementRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 输入验证单元测试
 */
class ValidationTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 测试调整请求验证 - 有效数据
     */
    public function test_adjustment_request_with_valid_data(): void
    {
        $request = new AdjustmentRequest();

        $data = [
            'user_id' => 1,
            'adjustment_type' => 'refund',
            'amount' => 100.50,
            'reason' => '测试退款',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
        $this->assertEmpty($validator->errors());
    }

    /**
     * 测试调整请求验证 - 缺少必填字段
     */
    public function test_adjustment_request_missing_required_fields(): void
    {
        $request = new AdjustmentRequest();

        $data = [
            'user_id' => 1,
            // 缺少 adjustment_type
            // 缺少 amount
            // 缺少 reason
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('adjustment_type', $validator->errors()->toArray());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
        $this->assertArrayHasKey('reason', $validator->errors()->toArray());
    }

    /**
     * 测试调整请求验证 - 无效的调整类型
     */
    public function test_adjustment_request_invalid_adjustment_type(): void
    {
        $request = new AdjustmentRequest();

        $data = [
            'user_id' => 1,
            'adjustment_type' => 'invalid_type',
            'amount' => 100.50,
            'reason' => '测试',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('adjustment_type', $validator->errors()->toArray());
    }

    /**
     * 测试调整请求验证 - 金额为负数
     */
    public function test_adjustment_request_negative_amount(): void
    {
        $request = new AdjustmentRequest();

        $data = [
            'user_id' => 1,
            'adjustment_type' => 'refund',
            'amount' => -100.50,
            'reason' => '测试',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    /**
     * 测试调整请求验证 - 金额为零
     */
    public function test_adjustment_request_zero_amount(): void
    {
        $request = new AdjustmentRequest();

        $data = [
            'user_id' => 1,
            'adjustment_type' => 'refund',
            'amount' => 0,
            'reason' => '测试',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('amount', $validator->errors()->toArray());
    }

    /**
     * 测试调整请求验证 - 用户 ID 不存在
     */
    public function test_adjustment_request_non_existent_user(): void
    {
        $request = new AdjustmentRequest();

        $data = [
            'user_id' => 99999,
            'adjustment_type' => 'refund',
            'amount' => 100.50,
            'reason' => '测试',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('user_id', $validator->errors()->toArray());
    }

    /**
     * 测试调整请求验证 - 原因过长
     */
    public function test_adjustment_request_reason_too_long(): void
    {
        $request = new AdjustmentRequest();

        $data = [
            'user_id' => 1,
            'adjustment_type' => 'refund',
            'amount' => 100.50,
            'reason' => str_repeat('测试', 300), // 超过 500 字符
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('reason', $validator->errors()->toArray());
    }

    /**
     * 测试结算请求验证 - 有效的周结算
     */
    public function test_settlement_request_valid_weekly_settlement(): void
    {
        $request = new SettlementRequest();

        $data = [
            'settlement_type' => 'weekly',
            'week_key' => '2025-W01',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
        $this->assertEmpty($validator->errors());
    }

    /**
     * 测试结算请求验证 - 有效的季度结算
     */
    public function test_settlement_request_valid_quarterly_settlement(): void
    {
        $request = new SettlementRequest();

        $data = [
            'settlement_type' => 'quarterly',
            'quarter_key' => '2025-Q1',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertFalse($validator->fails());
        $this->assertEmpty($validator->errors());
    }

    /**
     * 测试结算请求验证 - 无效的周期键格式
     */
    public function test_settlement_request_invalid_week_key_format(): void
    {
        $request = new SettlementRequest();

        $data = [
            'settlement_type' => 'weekly',
            'week_key' => 'invalid-format',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('week_key', $validator->errors()->toArray());
    }

    /**
     * 测试结算请求验证 - 无效的季度键格式
     */
    public function test_settlement_request_invalid_quarter_key_format(): void
    {
        $request = new SettlementRequest();

        $data = [
            'settlement_type' => 'quarterly',
            'quarter_key' => 'invalid-format',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('quarter_key', $validator->errors()->toArray());
    }

    /**
     * 测试结算请求验证 - 无效的季度（Q5）
     */
    public function test_settlement_request_invalid_quarter_number(): void
    {
        $request = new SettlementRequest();

        $data = [
            'settlement_type' => 'quarterly',
            'quarter_key' => '2025-Q5',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('quarter_key', $validator->errors()->toArray());
    }

    /**
     * 测试结算请求验证 - 缺少必填字段
     */
    public function test_settlement_request_missing_required_fields(): void
    {
        $request = new SettlementRequest();

        $data = [
            // 缺少 week_key 或 quarter_key
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        // 缺少 week_key 或 quarter_key 时，两者都应该有错误
        $errors = $validator->errors()->toArray();
        $this->assertArrayHasKey('week_key', $errors);
        $this->assertArrayHasKey('quarter_key', $errors);
    }

    /**
     * 测试结算请求验证 - 周结算缺少 week_key
     */
    public function test_settlement_request_weekly_missing_week_key(): void
    {
        $request = new SettlementRequest();

        $data = [
            'settlement_type' => 'weekly',
            // 缺少 week_key，但有 quarter_key，所以 week_key 不应该有错误
            'quarter_key' => '2025-Q1',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        // quarter_key 已提供，week_key 不应该有错误
        $this->assertFalse($validator->fails());
    }

    /**
     * 测试结算请求验证 - 季度结算缺少 quarter_key
     */
    public function test_settlement_request_quarterly_missing_quarter_key(): void
    {
        $request = new SettlementRequest();

        $data = [
            'settlement_type' => 'quarterly',
            // 缺少 quarter_key，但有 week_key，所以 quarter_key 不应该有错误
            'week_key' => '2025-W01',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        // week_key 已提供，quarter_key 不应该有错误
        $this->assertFalse($validator->fails());
    }

    /**
     * 测试结算请求验证 - 无效的结算类型
     */
    public function test_settlement_request_invalid_settlement_type(): void
    {
        $request = new SettlementRequest();

        $data = [
            'settlement_type' => 'invalid_type',
            'week_key' => '2025-W01',
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        // settlement_type 不在验证规则中，不应该有错误
        $this->assertFalse($validator->fails());
    }

    /**
     * 测试中文错误消息
     */
    public function test_chinese_error_messages(): void
    {
        $request = new AdjustmentRequest();

        $data = [
            'user_id' => 1,
            // 缺少 adjustment_type
        ];

        $validator = Validator::make($data, $request->rules(), $request->messages());

        $this->assertTrue($validator->fails());
        $errors = $validator->errors()->toArray();

        // 验证错误消息是中文
        $this->assertArrayHasKey('adjustment_type', $errors);
        $this->assertStringContainsString('调整类型', $errors['adjustment_type'][0]);
    }
}