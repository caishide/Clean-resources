<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\ApiAuth;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class ApiAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::fake();
    }

    /** @test */
    public function it_rejects_request_without_api_key_or_token()
    {
        $middleware = new ApiAuth();
        $request = Request::create('/api/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(401, $response->status());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('MISSING_AUTH', $data['code']);
    }

    /** @test */
    public function it_accepts_valid_api_key()
    {
        // Setup encrypted API key in config
        $validKey = 'test-api-key-12345';
        $encryptedKey = Crypt::encryptString($validKey);
        config(['app.api_key_encrypted' => $encryptedKey]);

        $middleware = new ApiAuth();
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-API-Key', $validKey);

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->status());
        $this->assertEquals('OK', $response->getContent());
    }

    /** @test */
    public function it_rejects_invalid_api_key()
    {
        // Setup encrypted API key in config
        $validKey = 'test-api-key-12345';
        $encryptedKey = Crypt::encryptString($validKey);
        config(['app.api_key_encrypted' => $encryptedKey]);

        $middleware = new ApiAuth();
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-API-Key', 'invalid-key');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(401, $response->status());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('INVALID_API_KEY', $data['code']);

        Log::channel('security')->shouldHaveReceived('warning')->once();
    }

    /** @test */
    public function it_accepts_valid_bearer_token()
    {
        $user = User::factory()->create([
            'api_token' => hash('sha256', 'valid-token-123'),
            'status' => 1,
        ]);

        $middleware = new ApiAuth();
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer valid-token-123');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(200, $response->status());
    }

    /** @test */
    public function it_rejects_invalid_bearer_token()
    {
        $middleware = new ApiAuth();
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer invalid-token');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(401, $response->status());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('INVALID_TOKEN', $data['code']);

        Log::channel('security')->shouldHaveReceived('warning')->once();
    }

    /** @test */
    public function it_handles_missing_api_key_configuration()
    {
        // Don't set api_key_encrypted in config
        config(['app.api_key_encrypted' => null]);

        $middleware = new ApiAuth();
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-API-Key', 'test-key');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertEquals(500, $response->status());
        $data = $response->getData(true);
        $this->assertFalse($data['success']);
        $this->assertEquals('CONFIG_ERROR', $data['code']);

        Log::channel('security')->shouldHaveReceived('warning')->once();
    }

    /** @test */
    public function it_sanitizes_log_data()
    {
        $user = User::factory()->create();
        $middleware = new ApiAuth();

        // Use reflection to access private method
        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('sanitizeLogData');
        $method->setAccessible(true);

        $data = [
            'password' => 'secret',
            'token' => 'abc123',
            'key' => 'xyz789',
            'safe_field' => 'visible',
        ];

        $sanitized = $method->invoke($middleware, $data);

        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('[REDACTED]', $sanitized['token']);
        $this->assertEquals('[REDACTED]', $sanitized['key']);
        $this->assertEquals('visible', $sanitized['safe_field']);
    }

    /** @test */
    public function it_handles_decryption_failure()
    {
        // Setup invalid encrypted API key in config
        config(['app.api_key_encrypted' => 'invalid-encrypted-data']);

        $middleware = new ApiAuth();
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('X-API-Key', 'test-key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt API key');

        $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        Log::channel('security')->shouldHaveReceived('error')->once();
    }
}
