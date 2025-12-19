<?php

/**
 * Monitoring Configuration
 *
 * Centralized monitoring and alerting configuration
 */

return [
    // Health check settings
    'health_check' => [
        'enabled' => env('HEALTH_CHECK_ENABLED', true),
        'interval' => env('HEALTH_CHECK_INTERVAL', 300), // 5 minutes
        'alert_threshold' => 3, // Number of consecutive failures before alerting
    ],

    // Performance monitoring
    'performance' => [
        'slow_query_threshold' => env('SLOW_QUERY_THRESHOLD', 1000), // milliseconds
        'memory_limit_threshold' => env('MEMORY_LIMIT_THRESHOLD', 80), // percentage
        'response_time_threshold' => env('RESPONSE_TIME_THRESHOLD', 2000), // milliseconds
    ],

    // Security monitoring
    'security' => [
        'failed_login_threshold' => env('FAILED_LOGIN_THRESHOLD', 5),
        'rate_limit_threshold' => env('RATE_LIMIT_THRESHOLD', 100),
        'alert_on_brute_force' => true,
        'alert_on_sql_injection' => true,
        'alert_on_xss_attempt' => true,
    ],

    // Error tracking
    'errors' => [
        'capture_silenced_errors' => true,
        'ignore_exceptions' => [
            'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
            'Illuminate\Database\Eloquent\ModelNotFoundException',
        ],
        'custom_error_messages' => [
            'password_compromised' => 'This password has been compromised',
            'rate_limit_exceeded' => 'Too many requests',
            'transaction_failed' => 'Transaction could not be completed',
            'payment_failed' => 'Payment processing failed',
            'account_suspended' => 'Account has been suspended',
        ],
    ],

    // Logging configuration
    'logging' => [
        'channels' => [
            'health' => [
                'driver' => 'daily',
                'path' => storage_path('logs/health.log'),
                'level' => 'info',
                'days' => 30,
            ],
            'security' => [
                'driver' => 'daily',
                'path' => storage_path('logs/security.log'),
                'level' => 'warning',
                'days' => 90,
            ],
            'performance' => [
                'driver' => 'daily',
                'path' => storage_path('logs/performance.log'),
                'level' => 'info',
                'days' => 30,
            ],
        ],
    ],

    // Alerting configuration
    'alerts' => [
        'email' => [
            'enabled' => env('ALERT_EMAIL_ENABLED', false),
            'recipients' => explode(',', env('ALERT_EMAIL_RECIPIENTS', '')),
            'from' => env('ALERT_EMAIL_FROM', 'alerts@binaryecom.com'),
        ],

        'slack' => [
            'enabled' => env('SLACK_ALERT_ENABLED', false),
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
            'channel' => env('SLACK_CHANNEL', '#alerts'),
            'username' => env('SLACK_USERNAME', 'BinaryEcom Bot'),
        ],

        'sms' => [
            'enabled' => env('SMS_ALERT_ENABLED', false),
            'recipients' => explode(',', env('SMS_RECIPIENTS', '')),
            'provider' => env('SMS_PROVIDER', 'twilio'),
        ],
    ],

    // Dashboard settings
    'dashboard' => [
        'enabled' => env('MONITORING_DASHBOARD_ENABLED', true),
        'refresh_interval' => env('DASHBOARD_REFRESH_INTERVAL', 30), // seconds
        'metrics_retention_days' => env('METRICS_RETENTION_DAYS', 30),
    ],

    // Database monitoring
    'database' => [
        'monitor_slow_queries' => true,
        'monitor_connection_pool' => true,
        'alert_on_connection_failure' => true,
    ],

    // Cache monitoring
    'cache' => [
        'monitor_hit_rate' => true,
        'min_hit_rate' => env('CACHE_MIN_HIT_RATE', 80), // percentage
        'alert_on_low_hit_rate' => true,
    ],

    // Queue monitoring
    'queue' => [
        'monitor_failed_jobs' => true,
        'max_failed_jobs' => env('MAX_FAILED_JOBS', 100),
        'alert_on_stuck_jobs' => true,
        'stuck_job_threshold' => env('STUCK_JOB_THRESHOLD', 3600), // seconds
    ],
];
