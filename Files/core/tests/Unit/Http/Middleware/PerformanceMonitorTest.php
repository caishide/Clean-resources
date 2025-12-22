<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\PerformanceMonitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PerformanceMonitorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function it_calculates_request_duration()
    {
        $middleware = new PerformanceMonitor();
        $request = Request::create('/test', 'GET');

        $startTime = microtime(true);
        $response = $middleware->handle($request, function ($req) {
            usleep(10000); // Sleep for 10ms
            return new Response('OK');
        });
        $endTime = microtime(true);

        $this->assertEquals(200, $response->status());
        $this->assertLessThanOrEqual($endTime - $startTime + 1, $response->headers->get('X-Response-Time'));
    }

    /** @test */
    public function it_tracks_memory_usage()
    {
        $middleware = new PerformanceMonitor();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertTrue($response->headers->has('X-Memory-Usage'));
        $this->assertNotNull($response->headers->get('X-Memory-Usage'));
    }

    /** @test */
    public function it_detects_n_plus_one_queries()
    {
        // Enable query logging
        DB::enableQueryLog();

        $middleware = new PerformanceMonitor();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            // Simulate N+1 query
            DB::table('users')->insert(['email' => 'test1@example.com', 'firstname' => 'Test', 'lastname' => 'User']);
            DB::table('users')->insert(['email' => 'test2@example.com', 'firstname' => 'Test', 'lastname' => 'User']);
            DB::table('users')->insert(['email' => 'test3@example.com', 'firstname' => 'Test', 'lastname' => 'User']);

            return new Response('OK');
        });

        $queryCount = count(DB::getQueryLog());
        $this->assertGreaterThan(0, $queryCount);
    }

    /** @test */
    public function it_logs_performance_metrics()
    {
        $middleware = new PerformanceMonitor();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        Log::channel('performance')->shouldHaveReceived('info')
            ->with('Performance metrics', \Mockery::on(function ($context) {
                return isset($context['duration']) &&
                       isset($context['memory_usage']) &&
                       isset($context['query_count']);
            }));
    }

    /** @test */
    public function it_sets_security_headers()
    {
        $middleware = new PerformanceMonitor();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            return new Response('OK');
        });

        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertTrue($response->headers->has('X-XSS-Protection'));
    }

    /** @test */
    public function it_calculates_queries_per_second()
    {
        $middleware = new PerformanceMonitor();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            // Simulate some queries
            DB::table('users')->insert(['email' => 'test@example.com', 'firstname' => 'Test', 'lastname' => 'User']);
            usleep(50000); // 50ms

            return new Response('OK');
        });

        $this->assertTrue($response->headers->has('X-Queries-Per-Second'));
    }

    /** @test */
    public function it_handles_slow_requests()
    {
        $middleware = new PerformanceMonitor();
        $request = Request::create('/test', 'GET');

        $response = $middleware->handle($request, function ($req) {
            usleep(500000); // 500ms - slow request
            return new Response('OK');
        });

        // Slow requests should still succeed
        $this->assertEquals(200, $response->status());

        // Should log warning for slow requests
        Log::channel('performance')->shouldHaveReceived('warning')
            ->with('Slow request detected', \Mockery::on(function ($context) {
                return isset($context['duration']) && $context['duration'] > 500;
            }));
    }
}
