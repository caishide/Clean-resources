<?php

namespace Tests\Unit\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\HealthController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HealthControllerTest extends TestCase
{
    protected HealthController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new HealthController();
    }

    /** @test */
    public function it_returns_healthy_status_when_all_checks_pass()
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

        $response = $this->controller->check();

        $data = $response->getData(true);

        $this->assertTrue($data['status']);
        $this->assertEquals('healthy', $data['message']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('cache', $data['checks']);
    }

    /** @test */
    public function it_handles_database_connection_failure()
    {
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new \Exception('Connection failed'));

        $response = $this->controller->check();

        $data = $response->getData(true);

        $this->assertFalse($data['status']);
        $this->assertEquals('unhealthy', $data['message']);
        $this->assertEquals('Connection failed', $data['checks']['database']['error']);
    }

    /** @test */
    public function it_handles_cache_failure()
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

        $response = $this->controller->check();

        $data = $response->getData(true);

        $this->assertFalse($data['status']);
        $this->assertEquals('unhealthy', $data['message']);
        $this->assertArrayHasKey('checks', $data);
        $this->assertEquals('Cache error', $data['checks']['cache']['error']);
    }

    /** @test */
    public function it_logs_health_check_result()
    {
        Log::spy();

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

        $response = $this->controller->check();

        Log::channel('system')->shouldHaveReceived('info')
            ->with('Health check performed', \Mockery::on(function ($context) {
                return isset($context['status']) && isset($context['checks']);
            }));
    }

    /** @test */
    public function it_returns_detailed_check_information()
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

        $response = $this->controller->check();

        $data = $response->getData(true);

        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('environment', $data);
        $this->assertArrayHasKey('checks', $data);
    }
}
