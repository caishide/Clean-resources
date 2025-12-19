<?php

namespace Tests\Feature;

use App\Models\User;
use App\Constants\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function user_can_register_with_valid_data()
    {
        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'referBy' => 'testuser',
            'position' => 1,
        ];

        $response = $this->post('/register', $userData);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);
    }

    /** @test */
    public function user_cannot_register_with_weak_password()
    {
        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'weak',
            'password_confirmation' => 'weak',
            'referBy' => 'testuser',
            'position' => 1,
        ];

        $response = $this->post('/register', $userData);

        $response->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', [
            'email' => 'john.doe@example.com',
        ]);
    }

    /** @test */
    public function user_cannot_register_with_duplicate_email()
    {
        // Create existing user
        User::factory()->create([
            'email' => 'john.doe@example.com',
        ]);

        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'referBy' => 'testuser',
            'position' => 1,
        ];

        $response = $this->post('/register', $userData);

        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function user_cannot_register_with_xss_in_input()
    {
        $userData = [
            'firstname' => '<script>alert("xss")</script>John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'referBy' => 'testuser',
            'position' => 1,
        ];

        $response = $this->post('/register', $userData);

        $response->assertRedirect();
        
        // Verify XSS is stripped
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'firstname' => 'John', // Script tags should be stripped
        ]);
    }

    /** @test */
    public function registration_rate_limiting_works()
    {
        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'referBy' => 'testuser',
            'position' => 1,
        ];

        // Attempt multiple registrations rapidly
        for ($i = 0; $i < 3; $i++) {
            $userData['email'] = "john{$i}.doe@example.com";
            $this->post('/register', $userData);
        }

        // Fourth attempt should be rate limited
        $userData['email'] = 'john4.doe@example.com';
        $response = $this->post('/register', $userData);

        $response->assertStatus(429); // Too Many Requests
    }

    /** @test */
    public function registration_creates_user_extra_and_login_log()
    {
        $userData = [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
            'referBy' => 'testuser',
            'position' => 1,
        ];

        $response = $this->post('/register', $userData);

        $user = User::where('email', 'john.doe@example.com')->first();
        
        // Check user extra was created
        $this->assertDatabaseHas('user_extras', [
            'user_id' => $user->id,
        ]);

        // Check login log was created
        $this->assertDatabaseHas('user_logins', [
            'user_id' => $user->id,
        ]);
    }
}
