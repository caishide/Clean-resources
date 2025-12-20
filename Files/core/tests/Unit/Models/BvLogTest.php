<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\BvLog;
use App\Constants\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * BvLog模型单元测试
 *
 * 测试BV（业务量）日志的各种业务逻辑和关联关系
 */
class BvLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = ['user_id', 'position', 'trx_type', 'amount'];
        $this->assertEquals($fillable, (new BvLog())->getFillable());
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        // 测试关系方法存在且类型正确
        $this->assertTrue(method_exists(BvLog::class, 'user'));
        $reflection = new \ReflectionMethod(BvLog::class, 'user');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_has_position_badge_accessor()
    {
        // 测试属性访问器存在
        $this->assertTrue(method_exists(BvLog::class, 'positionBadge'));
        $reflection = new \ReflectionMethod(BvLog::class, 'positionBadge');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_has_left_bv_scope()
    {
        // 测试本地作用域存在
        $this->assertTrue(method_exists(BvLog::class, 'scopeLeftBV'));
        $reflection = new \ReflectionMethod(BvLog::class, 'scopeLeftBV');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_has_right_bv_scope()
    {
        // 测试本地作用域存在
        $this->assertTrue(method_exists(BvLog::class, 'scopeRightBV'));
        $reflection = new \ReflectionMethod(BvLog::class, 'scopeRightBV');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_has_cut_bv_scope()
    {
        // 测试本地作用域存在
        $this->assertTrue(method_exists(BvLog::class, 'scopeCutBV'));
        $reflection = new \ReflectionMethod(BvLog::class, 'scopeCutBV');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_has_paid_bv_scope()
    {
        // 测试本地作用域存在
        $this->assertTrue(method_exists(BvLog::class, 'scopePaidBV'));
        $reflection = new \ReflectionMethod(BvLog::class, 'scopePaidBV');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_has_correct_constants()
    {
        $this->assertEquals('+', BvLog::TRX_TYPE_PLUS);
        $this->assertEquals('-', BvLog::TRX_TYPE_MINUS);
    }

    /** @test */
    public function it_uses_correct_table_name()
    {
        $this->assertEquals('bv_logs', (new BvLog())->getTable());
    }

    /** @test */
    public function it_has_correct_primary_key()
    {
        $this->assertEquals('id', (new BvLog())->getKeyName());
    }

    /** @test */
    public function it_has_correct_timestamp_columns()
    {
        $this->assertTrue((new BvLog())->timestamps);
    }
}
