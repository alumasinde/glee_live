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

        /*
        |--------------------------------------------------------------------------
        | Two-factor (TOTP) challenge
        |--------------------------------------------------------------------------
        |
        | Complements (never replaces) the IP+email limiter on /login. Consumed by
        | src/Middleware/RateLimitMiddleware.php as "RateLimitMiddleware:mfa", so
        | the key must contain no colon and no dash (dashes silently miss the key).
        |
        */

        'mfa' => [
            'max_attempts' => env('RATE_LIMIT_MFA_MAX', 10),
            'decay_minutes' => env('RATE_LIMIT_MFA_DECAY', 10),
        ],

        /*
        |--------------------------------------------------------------------------
        | Gate scanning
        |--------------------------------------------------------------------------
        |
        | Applied to the staff scanner (POST /gatepasses/scan) and the gate device
        | endpoint (POST /gate-scan) by key "scan" — no colon, no dash. The ceiling
        | is deliberately loose: a busy gate legitimately reads many badges per
        | minute, and this limiter is here to stop a script, not an operator.
        |
        */

        'scan' => [
            'max_attempts' => env('RATE_LIMIT_SCAN_MAX', 120),
            'decay_minutes' => env('RATE_LIMIT_SCAN_DECAY', 1),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Lockout (database-backed, per user row)
    |--------------------------------------------------------------------------
    |
    | users.failed_attempts is incremented on every bad password and every bad
    | OTP code; once it reaches max_attempts the row gets
    | locked_until = NOW() + INTERVAL <minutes> MINUTE and login is refused with
    | a generic message until the window passes. Both counters are cleared on a
    | successful authentication.
    |
    */

    'lockout' => [
        'max_attempts' => env('LOGIN_LOCKOUT_MAX_ATTEMPTS', 5),
        'minutes' => env('LOGIN_LOCKOUT_MINUTES', 15),
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
    | Two-Factor Authentication (RFC 6238 TOTP)
    |--------------------------------------------------------------------------
    |
    | 'issuer'                label shown by Google Authenticator / 1Password.
    | 'challenge_ttl'         seconds a password-verified session may sit in the
    |                         pre-auth "awaiting OTP" state.
    | 'setup_ttl'             seconds a pending (not yet enabled) secret stays in
    |                         session before enrolment must be restarted.
    | 'require_permission'    when true every self-service MFA action and UI
    |                         affordance is gated with can(<permission>). Keep it
    |                         false until an mfa.manage permission exists in
    |                         config/permissions.php + a grants migration.
    | 'encryption'            at-rest protection for users.mfa_secret.
    |                         APP_KEY / MFA_ENC_KEY win when present. When they
    |                         are absent the cipher derives per-user key material
    |                         from the row identity plus 'secret_pepper'; that
    |                         pepper MUST be replaced per install, otherwise a
    |                         full dump (rows + source) can decrypt every secret.
    |
    */

    'two_factor' => [
        'enabled' => env('TWO_FACTOR_ENABLED', true),
        'issuer' => env('TWO_FACTOR_ISSUER', env('APP_NAME', 'GatePass')),
        'digits' => 6,
        'period' => 30,
        'algorithm' => 'sha1',
        'window' => env('TWO_FACTOR_WINDOW', 1),
        'secret_bytes' => env('TWO_FACTOR_SECRET_BYTES', 20),
        'recovery_codes' => env('TWO_FACTOR_RECOVERY_CODES', 8),
        'challenge_ttl' => env('TWO_FACTOR_CHALLENGE_TTL', 300),
        'setup_ttl' => env('TWO_FACTOR_SETUP_TTL', 900),
        'require_permission' => env('MFA_REQUIRE_PERMISSION', false),
        'permission' => env('MFA_PERMISSION', 'mfa.manage'),
        'encryption' => [
            'cipher' => 'aes-256-gcm',
            'kdf' => 'sha256',
            'iterations' => env('MFA_KDF_ITERATIONS', 100000),
            'pepper' => env('MFA_SECRET_PEPPER', 'change-me-mfa-pepper'),
        ],
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
