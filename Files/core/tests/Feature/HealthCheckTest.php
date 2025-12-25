<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HealthCheckTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function health_check_returns_healthy_status()
    {
        DB::shouldReceive('connection')
            ->once()
            ->andReturnSelf();

        DB::connection()->shouldReceive('getPdo')
            ->once()
            ->andReturnSelf();

        Cache::shouldReceive('get')
            ->once()
            ->with('health_check')
            ->andReturn('ok');

        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertTrue($data['status']);
        $this->assertEquals('healthy', $data['message']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('environment', $data);
    }

    /** @test */
    public function health_check_handles_database_failure()
    {
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $response = $this->get('/api/health');

        $response->assertStatus(503);
        $data = $response->json();

        $this->assertFalse($data['status']);
        $this->assertEquals('unhealthy', $data['message']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertEquals('Database connection failed', $data['checks']['database']['error']);

        Log::channel('system')->shouldHaveReceived('error')->once();
    }

    /** @test */
    public function health_check_handles_cache_failure()
    {
        DB::shouldReceive('connection')
            ->once()
            ->andReturnSelf();

        DB::connection()->shouldReceive('getPdo')
            ->once()
            ->andReturnSelf();

        Cache::shouldReceive('get')
            ->once()
            ->with('health_check')
            ->andThrow(new \Exception('Cache error'));

        $response = $this->get('/api/health');

        $response->assertStatus(503);
        $data = $response->json();

        $this->assertFalse($data['status']);
        $this->assertEquals('unhealthy', $data['message']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('cache', $data['checks']);

        Log::channel('system')->shouldHaveReceived('error')->once();
    }

    /** @test */
    public function health_check_includes_detailed_information()
    {
        DB::shouldReceive('connection')
            ->once()
            ->andReturnSelf();

        DB::connection()->shouldReceive('getPdo')
            ->once()
            ->andReturnSelf();

        Cache::shouldReceive('get')
            ->once()
            ->with('health_check')
            ->andReturn('ok');

        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('cache', $data['checks']);

        // Database check should have status
        $this->assertArrayHasKey('status', $data['checks']['database']);
        $this->assertEquals('ok', $data['checks']['database']['status']);

        // Cache check should have status
        $this->assertArrayHasKey('status', $data['checks']['cache']);
        $this->assertEquals('ok', $data['checks']['cache']['status']);
    }

    /** @test */
    public function health_check_can_be_accessed_with_api_key()
    {
        $validKey = 'test-api-key';
        $encryptedKey = \Illuminate\Support\Facades\Crypt::encryptString($validKey);
        config(['app.api_key_encrypted' => $encryptedKey]);

        DB::shouldReceive('connection')
            ->once()
            ->andReturnSelf();

        DB::connection()->shouldReceive('getPdo')
            ->once()
            ->andReturnSelf();

        Cache::shouldReceive('get')
            ->once()
            ->with('health_check')
            ->andReturn('ok');

        $response = $this->withHeaders([
            'X-API-Key' => $validKey,
        ])->get('/api/health');

        $response->assertStatus(200);
    }

    /** @test */
    public function health_check_includes_uptime_information()
    {
        DB::shouldReceive('connection')
            ->once()
            ->andReturnSelf();

        DB::connection()->shouldReceive('getPdo')
            ->once()
            ->andReturnSelf();

        Cache::shouldReceive('get')
            ->once()
            ->with('health_check')
            ->andReturn('ok');

        $response = $this->get('/api/health');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertArrayHasKey('uptime', $data);
        $this->assertIsNumeric($data['uptime']);
    }
}
