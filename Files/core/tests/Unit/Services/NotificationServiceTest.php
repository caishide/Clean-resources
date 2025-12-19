<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class NotificationServiceTest extends TestCase
{
    /** @test */
    public function it_can_show_success_notification()
    {
        Session::shouldReceive('flash')
            ->once()
            ->with('notify', [
                ['success', 'Test success message']
            ]);

        $response = NotificationService::success('Test success message');

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /** @test */
    public function it_can_show_success_notification_with_custom_route()
    {
        Session::shouldReceive('flash')
            ->once()
            ->with('notify', [
                ['success', 'Test success message']
            ]);

        $response = NotificationService::success('Test success message', 'home');

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /** @test */
    public function it_can_show_error_notification()
    {
        Session::shouldReceive('flash')
            ->once()
            ->with('notify', [
                ['error', 'Test error message']
            ]);

        $response = NotificationService::error('Test error message');

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /** @test */
    public function it_can_show_error_notification_with_custom_route()
    {
        Session::shouldReceive('flash')
            ->once()
            ->with('notify', [
                ['error', 'Test error message']
            ]);

        $response = NotificationService::error('Test error message', 'dashboard');

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /** @test */
    public function it_can_show_warning_notification()
    {
        Session::shouldReceive('flash')
            ->once()
            ->with('notify', [
                ['warning', 'Test warning message']
            ]);

        $response = NotificationService::warning('Test warning message');

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    /** @test */
    public function it_can_show_info_notification()
    {
        Session::shouldReceive('flash')
            ->once()
            ->with('notify', [
                ['info', 'Test info message']
            ]);

        $response = NotificationService::info('Test info message');

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }
}
