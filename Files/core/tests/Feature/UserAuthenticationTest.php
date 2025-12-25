<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Constants\Status;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

/**
 * 用户认证功能测试
 *
 * 测试用户注册、登录、登出等核心功能
 */
class UserAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    /** @test */
    public function user_can_register_with_valid_data()
    {
        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'phone' => '+1234567890',
        ];

        $response = $this->post('/user/register', $userData);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
    }

    /** @test */
    public function user_cannot_register_with_duplicate_email()
    {
        User::factory()->create([
            'email' => 'john@example.com'
        ]);

        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        $response = $this->post('/user/register', $userData);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function user_cannot_register_with_weak_password()
    {
        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ];

        $response = $this->post('/user/register', $userData);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePass123!'),
            'status' => Status::USER_ACTIVE,
        ]);

        $response = $this->post('/user/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertRedirect('/user/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function user_cannot_login_with_invalid_credentials()
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePass123!'),
        ]);

        $response = $this->post('/user/login', [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    /** @test */
    public function user_cannot_login_when_banned()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePass123!'),
            'status' => Status::USER_BAN,
            'ban_reason' => 'Test ban',
        ]);

        $response = $this->post('/user/login', [
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest();
    }

    /** @test */
    public function user_can_logout()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post('/user/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /** @test */
    public function user_can_view_login_page()
    {
        $response = $this->get('/user/login');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.user.auth.login');
    }

    /** @test */
    public function user_can_view_register_page()
    {
        $response = $this->get('/user/register');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.user.auth.register');
    }

    /** @test */
    public function user_can_view_dashboard_after_login()
    {
        $user = User::factory()->create([
            'status' => Status::USER_ACTIVE,
        ]);
        $this->actingAs($user);

        $response = $this->get('/user/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.user.dashboard');
    }

    /** @test */
    public function user_cannot_access_dashboard_without_login()
    {
        $response = $this->get('/user/dashboard');

        $response->assertRedirect('/user/login');
    }

    /** @test */
    public function user_can_request_password_reset()
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
        ]);

        $response = $this->post('/user/password/email', [
            'email' => 'john@example.com',
        ]);

        $response->assertSessionHas('status');
    }

    /** @test */
    public function user_can_change_password_after_login()
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        $response = $this->post('/user/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertFalse(Hash::check('OldPassword123!', $user->password));
    }

    /** @test */
    public function user_cannot_change_password_with_wrong_current_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);

        $this->actingAs($user);

        $response = $this->post('/user/change-password', [
            'current_password' => 'WrongPassword',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors();
    }

    /** @test */
    public function user_can_verify_email()
    {
        $user = User::factory()->create([
            'ev' => Status::UNVERIFIED,
            'ver_code' => '123456',
        ]);

        $response = $this->get('/user/verify-email/123456');

        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertEquals(Status::VERIFIED, $user->ev);
    }

    /** @test */
    public function user_can_verify_phone()
    {
        $user = User::factory()->create([
            'sv' => Status::UNVERIFIED,
            'ver_code' => '123456',
        ]);

        $response = $this->get('/user/verify-phone/123456');

        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertEquals(Status::VERIFIED, $user->sv);
    }

    /** @test */
    public function user_can_view_profile()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/user/profile');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.user.profile');
    }

    /** @test */
    public function user_can_update_profile()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $profileData = [
            'firstname' => 'Jane',
            'lastname' => 'Smith',
            'phone' => '+1234567890',
            'address' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zip' => '10001',
        ];

        $response = $this->post('/user/profile/update', $profileData);

        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertEquals('Jane', $user->firstname);
        $this->assertEquals('Smith', $user->lastname);
    }

    /** @test */
    public function user_cannot_update_profile_with_xss()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $profileData = [
            'firstname' => '<script>alert("xss")</script>Jane',
            'lastname' => 'Smith',
        ];

        $response = $this->post('/user/profile/update', $profileData);

        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertEquals('Jane', $user->firstname);
        $this->assertNotContains('<script>', $user->firstname);
    }

    /** @test */
    public function user_can_view_transactions()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/user/transactions');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.user.transactions');
    }

    /** @test */
    public function user_can_view_orders()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/user/orders');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.user.orders');
    }

    /** @test */
    public function user_can_view_referrals()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/user/referrals');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.user.referrals');
    }

    /** @test */
    public function user_can_view_binary_tree()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/user/binary-tree');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.user.binary_tree');
    }

    /** @test */
    public function user_cannot_access_admin_area()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $response->assertStatus(403);
    }

    /** @test */
    public function guest_can_view_home_page()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.home');
    }

    /** @test */
    public function user_can_view_products()
    {
        $response = $this->get('/products');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.products');
    }

    /** @test */
    public function user_can_view_product_details()
    {
        $product = \App\Models\Product::factory()->create();

        $response = $this->get('/product/' . $product->id);

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.product_detail');
    }

    /** @test */
    public function user_can_add_product_to_cart()
    {
        $user = User::factory()->create();
        $product = \App\Models\Product::factory()->create();

        $this->actingAs($user);

        $response = $this->post('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertJson([
            'success' => true,
            'message' => 'Product added to cart successfully',
        ]);
    }

    /** @test */
    public function user_can_view_cart()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get('/cart');

        $response->assertStatus(200);
        $response->assertViewIs('templates.basic.cart');
    }

    /** @test */
    public function user_can_checkout()
    {
        $user = User::factory()->create([
            'balance' => 1000,
        ]);
        $product = \App\Models\Product::factory()->create([
            'price' => 100,
        ]);

        $this->actingAs($user);

        // 添加商品到购物车
        $this->post('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $response = $this->post('/checkout', [
            'payment_method' => 'balance',
        ]);

        $response->assertSessionHas('success');

        // 验证订单创建
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }
}
