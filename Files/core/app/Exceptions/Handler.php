<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;
use Sentry\Laravel\Integration;
use Illuminate\Support\Facades\Log;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            // Send exception to Sentry with additional context
            Integration::captureUnhandledException($e);

            // Log additional security-related information
            if ($this->isSecurityException($e)) {
                Log::channel('security')->error('Security exception occurred', [
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'request' => request()->all(),
                    'user_id' => auth()->id(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }
        });
    }

    /**
     * Determine if the exception is security-related
     */
    private function isSecurityException(Throwable $e): bool
    {
        $securityExceptions = [
            'CSRF',
            'authentication',
            'authorization',
            'password',
            'brute force',
            'rate limit',
        ];

        $message = strtolower($e->getMessage());
        foreach ($securityExceptions as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Report or log an exception.
     */
    public function report(Throwable $e)
    {
        // Add custom tags to Sentry for better grouping
        if (app()->bound('sentry')) {
            app('sentry')->configureScope(function ($scope) {
                // Add user context if authenticated
                if (auth()->check()) {
                    $scope->setUser([
                        'id' => auth()->id(),
                        'email' => auth()->user()->email,
                        'username' => auth()->user()->username,
                    ]);
                }

                // Add request context
                $scope->setTag('url', request()->fullUrl());
                $scope->setTag('method', request()->method());
                $scope->setTag('ip', request()->ip());

                // Add environment context
                $scope->setTag('environment', app()->environment());
            });
        }

        parent::report($e);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e)
    {
        // Don't expose sensitive information in production
        if (app()->environment('production')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'An error occurred. Please try again later.',
                    'code' => $e->getCode(),
                ], 500);
            }
        }

        return parent::render($request, $e);
    }
}
