<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Constants\Status;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;

/**
 * Product模型单元测试
 *
 * 测试产品的各种业务逻辑和关联关系
 */
class ProductTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->category = Category::factory()->create();
    }

    /** @test */
    public function it_has_correct_fillable_attributes()
    {
        $fillable = [
            'category_id',
            'name',
            'price',
            'quantity',
            'description',
            'meta_title',
            'meta_description',
            'meta_keyword',
            'thumbnail',
            'specifications',
            'bv',
            'is_featured',
            'status',
            'created_at',
            'updated_at',
        ];
        $this->assertEquals($fillable, (new Product())->getFillable());
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $casts = (new Product())->getCasts();
        $this->assertArrayHasKey('price', $casts);
        $this->assertArrayHasKey('bv', $casts);
        $this->assertArrayHasKey('specifications', $casts);
    }

    /** @test */
    public function it_belongs_to_a_category()
    {
        // 测试关系方法存在且类型正确
        $this->assertTrue(method_exists(Product::class, 'category'));
        $reflection = new \ReflectionMethod(Product::class, 'category');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_has_many_orders()
    {
        // 测试关系方法存在且类型正确
        $this->assertTrue(method_exists(Product::class, 'orders'));
        $reflection = new \ReflectionMethod(Product::class, 'orders');
        $this->assertTrue($reflection->isPublic());
    }

    /** @test */
    public function it_uses_correct_table_name()
    {
        $this->assertEquals('products', (new Product())->getTable());
    }

    /** @test */
    public function it_has_correct_primary_key()
    {
        $this->assertEquals('id', (new Product())->getKeyName());
    }

    /** @test */
    public function it_has_correct_timestamp_columns()
    {
        $this->assertTrue((new Product())->timestamps);
    }

    /** @test */
    public function it_can_check_if_product_is_active()
    {
        $activeProduct = Product::factory()->create(['status' => Status::PRODUCT_ACTIVE]);
        $inactiveProduct = Product::factory()->create(['status' => Status::PRODUCT_INACTIVE]);

        $this->assertTrue($activeProduct->isActive());
        $this->assertFalse($inactiveProduct->isActive());
    }

    /** @test */
    public function it_can_check_if_product_is_featured()
    {
        $featuredProduct = Product::factory()->create(['is_featured' => Status::FEATURED]);
        $regularProduct = Product::factory()->create(['is_featured' => Status::NOT_FEATURED]);

        $this->assertTrue($featuredProduct->isFeatured());
        $this->assertFalse($regularProduct->isFeatured());
    }

    /** @test */
    public function it_can_check_if_product_is_in_stock()
    {
        $inStockProduct = Product::factory()->create(['quantity' => 10]);
        $outOfStockProduct = Product::factory()->create(['quantity' => 0]);

        $this->assertTrue($inStockProduct->isInStock());
        $this->assertFalse($outOfStockProduct->isInStock());
    }

    /** @test */
    public function it_can_get_product_image_url()
    {
        $product = Product::factory()->create(['thumbnail' => 'test.jpg']);

        $this->assertTrue(method_exists($product, 'imageUrl'));
        $this->assertIsString($product->imageUrl());
    }

    /** @test */
    public function it_can_get_formatted_price()
    {
        $product = Product::factory()->create(['price' => 99.99]);

        $this->assertTrue(method_exists($product, 'priceFormatted'));
        $this->assertIsString($product->priceFormatted());
    }

    /** @test */
    public function it_can_get_product_short_description()
    {
        $product = Product::factory()->create([
            'description' => 'This is a very long product description that should be truncated for display purposes.'
        ]);

        $this->assertTrue(method_exists($product, 'shortDescription'));
        $this->assertIsString($product->shortDescription());
    }

    /** @test */
    public function it_can_scope_active_products()
    {
        Product::factory()->count(3)->create(['status' => Status::PRODUCT_ACTIVE]);
        Product::factory()->count(2)->create(['status' => Status::PRODUCT_INACTIVE]);

        $activeProducts = Product::active()->get();

        $this->assertCount(3, $activeProducts);
    }

    /** @test */
    public function it_can_scope_featured_products()
    {
        Product::factory()->count(2)->create(['is_featured' => Status::FEATURED]);
        Product::factory()->count(3)->create(['is_featured' => Status::NOT_FEATURED]);

        $featuredProducts = Product::featured()->get();

        $this->assertCount(2, $featuredProducts);
    }

    /** @test */
    public function it_can_scope_by_category()
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        Product::factory()->count(3)->create(['category_id' => $category1->id]);
        Product::factory()->count(2)->create(['category_id' => $category2->id]);

        $category1Products = Product::byCategory($category1->id)->get();
        $category2Products = Product::byCategory($category2->id)->get();

        $this->assertCount(3, $category1Products);
        $this->assertCount(2, $category2Products);
    }

    /** @test */
    public function it_can_scope_in_stock_products()
    {
        Product::factory()->count(3)->create(['quantity' => 10]);
        Product::factory()->count(2)->create(['quantity' => 0]);

        $inStockProducts = Product::inStock()->get();

        $this->assertCount(3, $inStockProducts);
    }

    /** @test */
    public function it_can_search_products_by_name()
    {
        Product::factory()->create(['name' => 'iPhone 15 Pro']);
        Product::factory()->create(['name' => 'Samsung Galaxy S24']);
        Product::factory()->create(['name' => 'iPhone 14']);

        $results = Product::search('iPhone')->get();

        $this->assertCount(2, $results);
    }

    /** @test */
    public function it_can_get_product_sales_count()
    {
        $product = Product::factory()->create();
        Order::factory()->count(5)->create([
            'product_id' => $product->id,
            'status' => Status::ORDER_COMPLETED
        ]);

        $salesCount = $product->salesCount();

        $this->assertEquals(5, $salesCount);
    }

    /** @test */
    public function it_can_get_product_total_revenue()
    {
        $product = Product::factory()->create(['price' => 100.00]);
        Order::factory()->count(3)->create([
            'product_id' => $product->id,
            'price' => 100.00,
            'quantity' => 1,
            'status' => Status::ORDER_COMPLETED
        ]);

        $totalRevenue = $product->totalRevenue();

        $this->assertEquals(300.00, $totalRevenue);
    }

    /** @test */
    public function it_can_get_product_average_rating()
    {
        $product = Product::factory()->create();
        // 模拟评价数据
        $product->reviews()->create([
            'user_id' => 1,
            'rating' => 5,
            'comment' => 'Great product'
        ]);
        $product->reviews()->create([
            'user_id' => 2,
            'rating' => 4,
            'comment' => 'Good product'
        ]);

        $avgRating = $product->averageRating();

        $this->assertEquals(4.5, $avgRating);
    }

    /** @test */
    public function it_can_get_product_review_count()
    {
        $product = Product::factory()->create();
        $product->reviews()->create([
            'user_id' => 1,
            'rating' => 5,
            'comment' => 'Great product'
        ]);
        $product->reviews()->create([
            'user_id' => 2,
            'rating' => 4,
            'comment' => 'Good product'
        ]);

        $reviewCount = $product->reviewCount();

        $this->assertEquals(2, $reviewCount);
    }

    /** @test */
    public function it_can_update_product_quantity()
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $product->updateQuantity(5);

        $this->assertEquals(5, $product->fresh()->quantity);
    }

    /** @test */
    public function it_can_decrement_product_quantity()
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $product->decrementQuantity(3);

        $this->assertEquals(7, $product->fresh()->quantity);
    }

    /** @test */
    public function it_can_increment_product_quantity()
    {
        $product = Product::factory()->create(['quantity' => 10]);

        $product->incrementQuantity(5);

        $this->assertEquals(15, $product->fresh()->quantity);
    }

    /** @test */
    public function it_can_get_low_stock_products()
    {
        Product::factory()->create(['quantity' => 10]);
        Product::factory()->create(['quantity' => 2]);
        Product::factory()->create(['quantity' => 1]);

        $lowStockProducts = Product::lowStock(5)->get();

        $this->assertCount(2, $lowStockProducts);
    }

    /** @test */
    public function it_can_get_out_of_stock_products()
    {
        Product::factory()->count(3)->create(['quantity' => 0]);
        Product::factory()->count(2)->create(['quantity' => 10]);

        $outOfStockProducts = Product::outOfStock()->get();

        $this->assertCount(3, $outOfStockProducts);
    }

    /** @test */
    public function it_can_scope_by_price_range()
    {
        Product::factory()->create(['price' => 50.00]);
        Product::factory()->create(['price' => 100.00]);
        Product::factory()->create(['price' => 150.00]);

        $midRangeProducts = Product::priceRange(75, 125)->get();

        $this->assertCount(1, $midRangeProducts);
        $this->assertEquals(100.00, $midRangeProducts->first()->price);
    }

    /** @test */
    public function it_can_get_product_tags()
    {
        $product = Product::factory()->create([
            'meta_keyword' => 'tag1, tag2, tag3'
        ]);

        $tags = $product->tags();

        $this->assertIsArray($tags);
        $this->assertContains('tag1', $tags);
        $this->assertContains('tag2', $tags);
        $this->assertContains('tag3', $tags);
    }
}
