<?php

/**
 * Sentry Configuration for Error Tracking and Performance Monitoring
 *
 * This file configures Sentry for comprehensive error tracking and performance monitoring.
 * Sentry helps identify and resolve production issues in real-time.
 */

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // Capture requests in test mode
    'capture_silenced_errors' => true,

    // Specify which environment this Sentry project is for
    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),

    // Sample rate for performance monitoring (0.0 to 1.0)
    'traces_sample_rate' => env('SENTRY_TRACES_SAMPLE_RATE', 0.1),

    // Capture queue job failures
    'capture_queue_job_failures' => true,

    // Database query tracing
    'database_query_buffer_threshold' => env('SENTRY_DATABASE_QUERY_BUFFER_THRESHOLD', 0),

    // Database query sample rate for tracing
    'database_query_sample_rate' => env('SENTRY_DATABASE_QUERY_SAMPLE_RATE', null),

    // Send PII (Personally Identifiable Information)
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // Custom error messages
    'custom_error_messages' => [
        // Example: Custom error types
        'password_compromised' => 'This password has been compromised in a data breach. Please choose a different password.',
        'rate_limit_exceeded' => 'Too many requests. Please try again later.',
        'transaction_failed' => 'Your transaction could not be completed. Please try again.',
        'payment_failed' => 'Payment processing failed. Please verify your payment method and try again.',
        'account_suspended' => 'Your account has been suspended. Please contact support for assistance.',
        'kyc_verification_failed' => 'KYC verification could not be completed. Please upload valid documents.',
    ],

    // Custom tags for better grouping
    'tags' => [
        'component' => 'user',
        'component' => 'admin',
        'component' => 'api',
        'component' => 'payment',
    ],

    // Ignore specific exceptions
    'ignore_exceptions' => [
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
    ],

    // Additional context
    'context_lines' => 5,

    // Determine if notifications should be sent when a server is down
    'send_attempted_span' => true,

    // Laravel queue configuration
    'breadcrumbs' => [
        // Capture SQL queries
        'sql_queries' => true,
        // Capture queue job information
        'queue_job' => true,
        // Capture command execution
        'command' => true,
    ],

];
