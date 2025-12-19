<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function rate_limiting_prevents_brute_force_login()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Attempt 5 failed logins
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        // Sixth attempt should be rate limited
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429); // Too Many Requests
    }

    /** @test */
    public function xss_is_prevented_in_profile_update()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user);

        $maliciousData = [
            'firstname' => '<script>alert("xss")</script>John',
            'lastname' => '<img src=x onerror=alert("xss")>Doe',
            'address' => '"><script>alert("xss")</script>',
        ];

        $response = $this->post('/profile-setting', $maliciousData);

        $response->assertRedirect();

        // Verify XSS is stripped
        $user->refresh();
        $this->assertEquals('John', $user->firstname);
        $this->assertEquals('Doe', $user->lastname);
        $this->assertNotContains('<script>', $user->address);
    }

    /** @test */
    public function path_traversal_is_prevented()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user);

        // Attempt to access file outside allowed directory
        $maliciousPath = '../../../etc/passwd';

        $response = $this->get("/download/{$maliciousPath}");

        $response->assertStatus(403); // Forbidden
    }

    /** @test */
    public function sql_injection_is_prevented()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Attempt SQL injection in search
        $maliciousInput = "'; DROP TABLE users; --";

        $response = $this->post('/search-user', ['username' => $maliciousInput]);

        $response->assertStatus(200);

        // Verify users table still exists
        $this->assertTrue(\Schema::hasTable('users'));
    }

    /** @test */
    public function csrf_protection_is_enabled()
    {
        // Attempt to submit form without CSRF token
        $response = $this->post('/profile-setting', [
            'firstname' => 'John',
            'lastname' => 'Doe',
        ]);

        $response->assertStatus(419); // CSRF token mismatch
    }

    /** @test */
    public function session_fixation_is_prevented()
    {
        // First request to get session
        $response1 = $this->get('/login');
        $sessionId1 = session()->getId();

        // Login
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response2 = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $sessionId2 = session()->getId();

        // Session ID should change after login
        $this->assertNotEquals($sessionId1, $sessionId2);
    }

    /** @test */
    public function sensitive_data_is_not_exposed_in_responses()
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret'),
        ]);

        // Access user via API
        $response = $this->get('/api/v1/user/profile');

        $response->assertStatus(401); // Unauthenticated

        $this->actingAs($user);

        $response = $this->get('/api/v1/user/profile');

        $response->assertStatus(200);

        $data = $response->json();

        // Password should not be in response
        $this->assertArrayNotHasKey('password', $data['data']);
        
        // Other sensitive fields should not be exposed
        $this->assertArrayNotHasKey('password_changed_at', $data['data']);
    }

    /** @test */
    public function password_compromised_check_works()
    {
        // Attempt to register with a known compromised password
        $response = $this->post('/register', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123', // Common compromised password
            'password_confirmation' => 'password123',
            'referBy' => 'testuser',
            'position' => 1,
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertStringContainsString('compromised', session('errors')->first('password'));
    }

    /** @test */
    public function file_upload_is_secure()
    {
        $user = User::factory()->create();
        
        $this->actingAs($user);

        // Attempt to upload a file with malicious extension
        $response = $this->post('/profile-setting', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'image' => new \Illuminate\Http\UploadedFile(
                resource_path('test-files/malicious.php'),
                'malicious.php',
                'application/x-php',
                null,
                true
            ),
        ]);

        $response->assertSessionHasErrors('image');
    }

    /** @test */
    public function http_headers_are_secure()
    {
        $response = $this->get('/');

        // Check for security headers
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
