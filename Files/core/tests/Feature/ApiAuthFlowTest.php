<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ApiAuthFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function api_requires_authentication()
    {
        $response = $this->get('/api/v1/health');

        $response->assertStatus(401);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertEquals('MISSING_AUTH', $data['code']);
    }

    /** @test */
    public function api_accepts_valid_api_key()
    {
        $validKey = 'test-api-key-12345';
        $encryptedKey = Crypt::encryptString($validKey);
        config(['app.api_key_encrypted' => $encryptedKey]);

        $response = $this->withHeaders([
            'X-API-Key' => $validKey,
        ])->get('/api/v1/health');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['status']);
    }

    /** @test */
    public function api_rejects_invalid_api_key()
    {
        $validKey = 'test-api-key-12345';
        $encryptedKey = Crypt::encryptString($validKey);
        config(['app.api_key_encrypted' => $encryptedKey]);

        $response = $this->withHeaders([
            'X-API-Key' => 'invalid-key',
        ])->get('/api/v1/health');

        $response->assertStatus(401);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertEquals('INVALID_API_KEY', $data['code']);

        Log::channel('security')->shouldHaveReceived('warning')->once();
    }

    /** @test */
    public function api_accepts_valid_bearer_token()
    {
        $user = User::factory()->create([
            'api_token' => hash('sha256', 'valid-token-123'),
            'status' => 1,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer valid-token-123',
        ])->get('/api/v1/health');

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertTrue($data['status']);
    }

    /** @test */
    public function api_rejects_expired_bearer_token()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid-token',
        ])->get('/api/v1/health');

        $response->assertStatus(401);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertEquals('INVALID_TOKEN', $data['code']);

        Log::channel('security')->shouldHaveReceived('warning')->once();
    }

    /** @test */
    public function api_rejects_request_without_any_authentication()
    {
        $response = $this->get('/api/v1/health');

        $response->assertStatus(401);
        $data = $response->json();
        $this->assertFalse($data['success']);
        $this->assertEquals('MISSING_AUTH', $data['code']);
    }

    /** @test */
    public function rate_limiting_works_on_api_endpoints()
    {
        $user = User::factory()->create([
            'api_token' => hash('sha256', 'test-token'),
            'status' => 1,
        ]);

        // Make 5 requests
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer test-token',
            ])->get('/api/v1/health');
            $this->assertEquals(200, $response->status());
        }

        // Sixth request should be rate limited
        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-token',
        ])->get('/api/v1/health');

        $response->assertStatus(429);
    }

    /** @test */
    public function sensitive_data_is_not_logged()
    {
        $validKey = 'test-api-key-12345';
        $encryptedKey = Crypt::encryptString($validKey);
        config(['app.api_key_encrypted' => $encryptedKey]);

        $response = $this->withHeaders([
            'X-API-Key' => 'wrong-key',
        ])->get('/api/v1/health');

        Log::channel('security')->shouldHaveReceived('warning')
            ->with(\Mockery::on(function ($message) {
                return !str_contains($message, 'test-api-key-12345') &&
                       !str_contains($message, 'X-API-Key');
            }));
    }
}
