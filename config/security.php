<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */

    'rate_limiting' => [
        'enabled' => env('RATE_LIMIT_ENABLED', true),

        'login' => [
            'max_attempts' => env('RATE_LIMIT_LOGIN_MAX', 5),
            'decay_minutes' => env('RATE_LIMIT_LOGIN_DECAY', 15),
        ],

        'password_reset' => [
            'max_attempts' => env('RATE_LIMIT_PASSWORD_RESET_MAX', 3),
            'decay_minutes' => env('RATE_LIMIT_PASSWORD_RESET_DECAY', 60),
        ],

        'api' => [
            'max_attempts' => env('RATE_LIMIT_API_MAX', 60),
            'decay_minutes' => env('RATE_LIMIT_API_DECAY', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Security
    |--------------------------------------------------------------------------
    */

    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 120), // minutes
        'secure' => env('SESSION_SECURE', env('APP_ENV') === 'production'),
        'httponly' => true,
        'samesite' => 'Strict',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */

    'csrf' => [
        'enabled' => env('CSRF_ENABLED', true),
        'token_lifetime' => env('CSRF_TOKEN_LIFETIME', 7200), // seconds (2 hours)
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    */

    'password' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 12),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_special_chars' => env('PASSWORD_REQUIRE_SPECIAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    */

    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', false),
        'report_uri' => env('CSP_REPORT_URI', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers
    |--------------------------------------------------------------------------
    */

    'headers' => [
        'hsts' => [
            'enabled' => env('HSTS_ENABLED', env('APP_ENV') === 'production'),
            'max_age' => env('HSTS_MAX_AGE', 31536000), // 1 year
            'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', true),
            'preload' => env('HSTS_PRELOAD', true),
        ],
    ],

];
