<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Blueprint
    |--------------------------------------------------------------------------
    |
    | module => actions[]
    |
    | These are used ONLY for seeding the database.
    |
    */

    'dashboard' => [
        'access'
    ],

    'gatepass' => [
        'create',
        'view',
        'update',
        'delete',
        'approve',
        'checkin',
        'checkout',
        'print'
    ],

    'visitors' => [
        'create',
        'view',
        'update',
        'blacklist'
    ],

    'users' => [
        'create',
        'view',
        'update',
        'disable'
    ],

    'roles' => [
        'create',
        'assign',
        'update'
    ],

    'settings' => [
        'update'
    ],

    'audit' => [
        'view'
    ]

];
