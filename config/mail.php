<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mail Configuration
    |--------------------------------------------------------------------------
    */

    'host' => env('MAIL_HOST', 'smtp.example.com'),

    'port' => env('MAIL_PORT', 587),

    'encryption' => env('MAIL_ENCRYPTION', 'tls'), // tls, ssl, or null

    'username' => env('MAIL_USERNAME', ''),

    'password' => env('MAIL_PASSWORD', ''),

    'from_address' => env('MAIL_FROM_ADDRESS', 'noreply@example.com'),

    'from_name' => env('MAIL_FROM_NAME', config('app.name')),

    'insecure_tls' => env('MAIL_INSECURE_TLS', false),

];
