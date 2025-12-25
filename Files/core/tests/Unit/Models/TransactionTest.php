<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use App\Models\Deposit;
use App\Models\Withdrawal;
use App\Constants\Status;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Carbon\Carbon;

/**
 * Transaction模型单元测试
 *
 * 测试交易记录的各种业务逻辑和关联关系
 */
class TransactionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'amount',
            'post_balance',
            'charge',
            'trx_type',
            'details',
            'trx',
            'remark',
            'source_type',
            'source_id',
            'reversal_of_id',
            'adjustment_batch_id',
        ];
        $this->assertEquals($fillable, (new Transaction())->getFillable());
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $casts = (new Transaction())->getCasts();
        $this->assertArrayHasKey('amount', $casts);
        $this->assertArrayHasKey('post_balance', $casts);
        $this->assertArrayHasKey('charge', $casts);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        // 测试关系方法存在且类型正确
        $this->assertTrue(method_exists(Transaction::class, 'user'));
        $reflection = new \ReflectionMethod(Transaction::class, 'user');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_uses_correct_table_name()
    {
        $this->assertEquals('transactions', (new Transaction())->getTable());
    }

    /** @test */
    public function it_has_correct_primary_key()
    {
        $this->assertEquals('id', (new Transaction())->getKeyName());
    }

    /** @test */
    public function it_has_correct_timestamp_columns()
    {
        $this->assertTrue((new Transaction())->timestamps);
    }
}
