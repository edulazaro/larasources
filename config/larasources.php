<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Duration
    |--------------------------------------------------------------------------
    |
    | The default cache duration for source data in minutes.
    |
    */

    'cache_duration' => env('LARASOURCES_CACHE_DURATION', 60),

    /*
    |--------------------------------------------------------------------------
    | Origins Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for different external API origins.
    |
    */

    'origins' => [

        // Define origin-specific configuration here, keyed by the origin alias.
        // Example:
        //
        // 'my_provider' => [
        //     'api_key' => env('MY_PROVIDER_API_KEY'),
        //     'secret'  => env('MY_PROVIDER_SECRET'),
        //     'sandbox' => env('MY_PROVIDER_SANDBOX', false),
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting configuration for API calls to prevent hitting limits.
    |
    */

    'rate_limiting' => [
        'enabled' => env('LARASOURCES_RATE_LIMITING', true),
        'max_attempts' => env('LARASOURCES_MAX_ATTEMPTS', 100),
        'decay_minutes' => env('LARASOURCES_DECAY_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic retries on failed API calls.
    |
    */

    'retry' => [
        'enabled' => env('LARASOURCES_RETRY_ENABLED', true),
        'max_attempts' => env('LARASOURCES_RETRY_MAX_ATTEMPTS', 3),
        'delay' => env('LARASOURCES_RETRY_DELAY', 1000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configuration for logging API calls and errors.
    |
    */

    'logging' => [
        'enabled' => env('LARASOURCES_LOGGING_ENABLED', true),
        'channel' => env('LARASOURCES_LOG_CHANNEL', 'stack'),
        'level' => env('LARASOURCES_LOG_LEVEL', 'info'),
    ],

];