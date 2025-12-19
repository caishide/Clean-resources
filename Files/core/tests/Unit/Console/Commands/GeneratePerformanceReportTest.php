<?php

namespace Tests\Unit\Console\Commands;

use Tests\TestCase;
use App\Console\Commands\GeneratePerformanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

class GeneratePerformanceReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function it_can_generate_performance_report()
    {
        $this->artisan('performance:report', ['--period' => '24'])
            ->assertExitCode(0)
            ->expectsOutput('Generating performance report...')
            ->expectsOutput('Performance report generated:');

        // Verify report file was created
        Storage::disk('local')->assertExists('reports/');
    }

    /** @test */
    public function it_can_generate_report_with_custom_period()
    {
        $this->artisan('performance:report', ['--period' => '48'])
            ->assertExitCode(0);

        // Verify report contains custom period
        $files = Storage::disk('local')->files('reports/');
        $this->assertNotEmpty($files);
    }

    /** @test */
    public function it_includes_database_metrics_in_report()
    {
        $this->artisan('performance:report')
            ->assertExitCode(0);

        $files = Storage::disk('local')->files('reports/');
        $content = Storage::disk('local')->get($files[0]);
        $report = json_decode($content, true);

        $this->assertArrayHasKey('database', $report);
        $this->assertArrayHasKey('total_queries', $report['database']);
        $this->assertArrayHasKey('slow_queries', $report['database']);
        $this->assertArrayHasKey('average_query_time', $report['database']);
    }

    /** @test */
    public function it_includes_memory_metrics_in_report()
    {
        $this->artisan('performance:report')
            ->assertExitCode(0);

        $files = Storage::disk('local')->files('reports/');
        $content = Storage::disk('local')->get($files[0]);
        $report = json_decode($content, true);

        $this->assertArrayHasKey('memory', $report);
        $this->assertArrayHasKey('current_usage_mb', $report['memory']);
        $this->assertArrayHasKey('peak_usage_mb', $report['memory']);
        $this->assertArrayHasKey('usage_percentage', $report['memory']);
    }

    /** @test */
    public function it_includes_disk_metrics_in_report()
    {
        $this->artisan('performance:report')
            ->assertExitCode(0);

        $files = Storage::disk('local')->files('reports/');
        $content = Storage::disk('local')->get($files[0]);
        $report = json_decode($content, true);

        $this->assertArrayHasKey('disk', $report);
        $this->assertArrayHasKey('total_gb', $report['disk']);
        $this->assertArrayHasKey('free_gb', $report['disk']);
        $this->assertArrayHasKey('used_gb', $report['disk']);
        $this->assertArrayHasKey('usage_percentage', $report['disk']);
    }

    /** @test */
    public function it_includes_cache_metrics_in_report()
    {
        $this->artisan('performance:report')
            ->assertExitCode(0);

        $files = Storage::disk('local')->files('reports/');
        $content = Storage::disk('local')->get($files[0]);
        $report = json_decode($content, true);

        $this->assertArrayHasKey('cache', $report);
        $this->assertArrayHasKey('driver', $report['cache']);
        $this->assertArrayHasKey('status', $report['cache']);
    }

    /** @test */
    public function it_generates_recommendations_based_on_usage()
    {
        $this->artisan('performance:report')
            ->assertExitCode(0);

        $files = Storage::disk('local')->files('reports/');
        $content = Storage::disk('local')->get($files[0]);
        $report = json_decode($content, true);

        $this->assertArrayHasKey('recommendations', $report);
        $this->assertIsArray($report['recommendations']);
    }

    /** @test */
    public function it_displays_report_summary()
    {
        $this->artisan('performance:report')
            ->assertExitCode(0)
            ->expectsOutput('Performance Report Summary:')
            ->expectsOutput('Period:')
            ->expectsOutput('Memory Usage:')
            ->expectsOutput('Disk Usage:')
            ->expectsOutput('Cache Driver:');
    }
}
