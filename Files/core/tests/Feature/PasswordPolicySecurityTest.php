<?php

namespace Tests\Feature;

use App\Constants\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordPolicySecurityTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_password_must_be_at_least_8_characters()
    {
        $response = $this->post(route('user.register'), [
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@test.com',
            'phone' => '+1234567890',
            'password' => 'weak', // Less than 8 characters
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function user_password_must_contain_uppercase()
    {
        $response = $this->post(route('user.register'), [
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@test.com',
            'phone' => '+1234567890',
            'password' => 'lowercase123', // No uppercase
            'password_confirmation' => 'lowercase123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function user_password_must_contain_lowercase()
    {
        $response = $this->post(route('user.register'), [
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@test.com',
            'phone' => '+1234567890',
            'password' => 'UPPERCASE123', // No lowercase
            'password_confirmation' => 'UPPERCASE123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function user_password_must_contain_number()
    {
        $response = $this->post(route('user.register'), [
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@test.com',
            'phone' => '+1234567890',
            'password' => 'NoNumbers', // No numbers
            'password_confirmation' => 'NoNumbers',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function user_password_must_contain_special_character()
    {
        $response = $this->post(route('user.register'), [
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@test.com',
            'phone' => '+1234567890',
            'password' => 'NoSpecialChars123', // No special chars
            'password_confirmation' => 'NoSpecialChars123',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function valid_user_password_is_accepted()
    {
        $response = $this->post(route('user.register'), [
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@test.com',
            'phone' => '+1234567890',
            'password' => 'StrongPass123!', // Meets all requirements
            'password_confirmation' => 'StrongPass123!',
        ]);

        $response->assertSessionDoesntHaveErrors('password');
    }

    /** @test */
    public function admin_password_must_be_at_least_10_characters()
    {
        $admin = \App\Models\Admin::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'username' => 'admin',
            'password' => bcrypt('oldpassword'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.password.update'), [
                'old_password' => 'oldpassword',
                'password' => 'weak123', // Less than 10 characters
                'password_confirmation' => 'weak123',
            ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function admin_password_must_contain_all_required_character_types()
    {
        $admin = \App\Models\Admin::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'username' => 'admin',
            'password' => bcrypt('oldpassword'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.password.update'), [
                'old_password' => 'oldpassword',
                'password' => 'weakpassword', // Missing uppercase, number, special char
                'password_confirmation' => 'weakpassword',
            ]);

        $response->assertSessionHasErrors('password');
    }

    /** @test */
    public function valid_admin_password_is_accepted()
    {
        $admin = \App\Models\Admin::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'username' => 'admin',
            'password' => bcrypt('oldpassword'),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->post(route('admin.password.update'), [
                'old_password' => 'oldpassword',
                'password' => 'StrongAdmin123!', // Meets all requirements
                'password_confirmation' => 'StrongAdmin123!',
            ]);

        $response->assertSessionDoesntHaveErrors('password');
    }

    /** @test */
    public function password_reset_enforces_policy()
    {
        $user = User::create([
            'firstname' => 'Test',
            'lastname' => 'User',
            'username' => 'testuser',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
            'status' => Status::USER_ACTIVE,
            'ev' => Status::VERIFIED,
            'sv' => Status::VERIFIED,
            'tv' => Status::VERIFIED,
        ]);

        $response = $this->post(route('user.password.email'), [
            'email' => 'test@test.com',
        ]);

        // Simulate password reset with weak password
        $response = $this->post(route('user.password.reset'), [
            'email' => 'test@test.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ]);

        $response->assertSessionHasErrors('password');
    }
}
