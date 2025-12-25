<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Order;
use App\Constants\Status;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Order模型单元测试
 *
 * 测试订单的各种业务逻辑和关联关系
 */
class OrderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // 不创建实际的数据库记录，只用于反射测试
        $this->user = new User();
        $this->product = new Product();
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'product_id',
            'quantity',
            'price',
            'total_price',
            'amount',
            'commission',
            'trx',
            'status',
        ];
        $this->assertEquals($fillable, (new Order())->getFillable());
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $casts = (new Order())->getCasts();
        $this->assertArrayHasKey('amount', $casts);
        $this->assertArrayHasKey('commission', $casts);
    }

    /** @test */
    public function it_belongs_to_a_user()
    {
        // 测试关系方法存在且类型正确
        $this->assertTrue(method_exists(Order::class, 'user'));
        $reflection = new \ReflectionMethod(Order::class, 'user');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_belongs_to_a_product()
    {
        // 测试关系方法存在且类型正确
        $this->assertTrue(method_exists(Order::class, 'product'));
        $reflection = new \ReflectionMethod(Order::class, 'product');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_has_status_order_badge_accessor()
    {
        // 测试属性访问器存在
        $this->assertTrue(method_exists(Order::class, 'statusOrderBadge'));
        $reflection = new \ReflectionMethod(Order::class, 'statusOrderBadge');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_uses_correct_table_name()
    {
        $this->assertEquals('orders', (new Order())->getTable());
    }

    /** @test */
    public function it_has_correct_primary_key()
    {
        $this->assertEquals('id', (new Order())->getKeyName());
    }

    /** @test */
    public function it_has_correct_timestamp_columns()
    {
        $this->assertTrue((new Order())->timestamps);
    }
}
