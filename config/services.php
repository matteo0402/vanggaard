<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discogs' => [
        'base_url' => env('DISCOGS_API_URL', 'https://api.discogs.com'),
        'user_agent' => env('DISCOGS_USER_AGENT', 'Vanggaard/1.0 +https://github.com/matteo0402/vanggaard'),
        'consumer_key' => env('DISCOGS_CONSUMER_KEY', ''),
        'consumer_secret' => env('DISCOGS_CONSUMER_SECRET', ''),
        'connect_timeout' => (int) env('DISCOGS_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('DISCOGS_TIMEOUT', 10),
        'requests_per_minute' => (int) env('DISCOGS_REQUESTS_PER_MINUTE', 50),
        'retry_attempts' => (int) env('DISCOGS_RETRY_ATTEMPTS', 3),
        'retry_base_delay' => (int) env('DISCOGS_RETRY_BASE_DELAY', 250),
        'retry_max_delay' => (int) env('DISCOGS_RETRY_MAX_DELAY', 5000),
        'stale_after_hours' => (int) env('DISCOGS_STALE_AFTER_HOURS', 4),
        'stale_refresh_budget' => (int) env('DISCOGS_STALE_REFRESH_BUDGET', 10),
        'refresh_failure_cooldown_minutes' => (int) env('DISCOGS_REFRESH_FAILURE_COOLDOWN_MINUTES', 60),
        'high_priority_queue' => env('DISCOGS_HIGH_PRIORITY_QUEUE', 'high'),
    ],

];
