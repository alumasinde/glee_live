<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application
    |--------------------------------------------------------------------------
    */

    'name' => 'GatePass Management System',

    'env' => env('APP_ENV', 'local'), // local | staging | production

    'debug' => env('APP_DEBUG', env('APP_ENV') !== 'production'),

    /*
    | The base URL used to build links that leave the request (password reset
    | mail, cron escalation notices). APP_URL wins so a CLI job and a web
    | request agree; without it a web request falls back to the origin it was
    | served on, and a CLI job to localhost.
    */

    'url' => (static function (): string {
        $configured = env('APP_URL');

        if (is_string($configured) && trim($configured) !== '') {
            return rtrim(trim($configured), '/');
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';

        if ($host !== '') {
            $https = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

            return ($https ? 'https' : 'http') . '://' . $host;
        }

        return 'http://localhost';
    })(),

    'timezone' => 'Africa/Nairobi',

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */

    'session_lifetime' => 120, // minutes

    'bcrypt_rounds' => 12,

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenant
    |--------------------------------------------------------------------------
    */

    'multi_tenant' => true,

];
