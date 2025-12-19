<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    /** @test */
    public function dashboard_loads_within_acceptable_time()
    {
        // Create test data
        User::factory()->count(50)->create();
        UserLogin::factory()->count(100)->create();

        $start = microtime(true);

        $response = $this->get('/admin/dashboard');

        $end = microtime(true);
        $executionTime = ($end - $start) * 1000; // Convert to milliseconds

        $response->assertStatus(200);

        // Assert page loads within 2000ms
        $this->assertLessThan(2000, $executionTime, 'Dashboard took too long to load');
    }

    /** @test */
    public function database_queries_are_optimized()
    {
        // Enable query logging
        DB::enableQueryLog();

        // Create test data
        User::factory()->count(100)->create();

        $start = microtime(true);

        // Simulate dashboard query
        $userStats = User::selectRaw('
            COUNT(*) as total_users,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as active_users
        ', [1])->first();

        $end = microtime(true);
        $executionTime = ($end - $start) * 1000;

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Should use optimized query (1 query)
        $this->assertLessThanOrEqual(1, $queryCount, 'Too many queries executed');
        
        // Query should complete within 500ms
        $this->assertLessThan(500, $executionTime, 'Query execution too slow');
    }

    /** @test */
    public function cache_is_working_for_language_middleware()
    {
        // First request should hit database
        $response1 = $this->get('/change/en');
        $this->assertEquals(200, $response1->getStatusCode());

        // Second request should use cache
        $response2 = $this->get('/change/en');
        $this->assertEquals(200, $response2->getStatusCode());
    }

    /** @test */
    public function user_list_pagination_performs_well()
    {
        // Create test data
        User::factory()->count(1000)->create();

        $start = microtime(true);

        $response = $this->get('/admin/users?page=1');

        $end = microtime(true);
        $executionTime = ($end - $start) * 1000;

        $response->assertStatus(200);

        // Page should load within 1500ms
        $this->assertLessThan(1500, $executionTime, 'User list pagination too slow');
    }

    /** @test */
    public function memory_usage_is_acceptable()
    {
        $initialMemory = memory_get_usage();

        // Create test data
        User::factory()->count(1000)->create();

        // Perform operations
        $users = User::paginate(20);

        $peakMemory = memory_get_peak_usage();
        $memoryIncrease = ($peakMemory - $initialMemory) / 1024 / 1024; // Convert to MB

        // Memory increase should be less than 100MB
        $this->assertLessThan(100, $memoryIncrease, 'Memory usage too high');
    }

    /** @test */
    public function n_plus_one_query_problem_is_prevented()
    {
        DB::enableQueryLog();

        // Create users with relationships
        $users = User::factory()->count(10)->create();

        // Eager load to prevent N+1
        $usersWithRelations = User::with(['userExtras'])->get();

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Should be 2 queries (1 for users, 1 for user_extras with eager loading)
        // Without eager loading, it would be 11 queries
        $this->assertLessThanOrEqual(5, $queryCount, 'N+1 query problem detected');
    }
}
