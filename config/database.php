<?php

return [

    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [

        'mysql' => [

            'dsn' => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                env('DB_HOST', '127.0.0.1'),
                env('DB_PORT', '3306'),
                env('DB_DATABASE'),
                env('DB_CHARSET', 'utf8mb4')
            ),

            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),

            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        ],

    ],

];
