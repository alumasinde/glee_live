<?php

/*
|--------------------------------------------------------------------------
| Permission Blueprint — single source of truth for the permission matrix
|--------------------------------------------------------------------------
|
| module => actions[]
|
| Mirrored by database/migrations/0012_permissions_matrix.sql.
| If you change this file, ship a matching migration; runtime checks
| read the database, never this file (fully dynamic roles).
|
*/

return [

    'dashboard' => [
        'access',
    ],

    'gatepass' => [
        'view',       // department-scoped listing
        'view_all',   // org-wide listing
        'create',
        'update',
        'delete',
        'checkin',
        'checkout',
        'scan',
        'print',
    ],

    'approval' => [
        'view',
        'approve',
        'reject',
    ],

    'visitors' => [
        'view',
        'create',
        'update',
        'delete',
        'blacklist',
        'issue_badge',
    ],

    'visits' => [
        'view',
        'create',
        'check_in',
        'check_out',
        'export',
    ],

    'reports' => [
        'view',
        'export',
    ],

    'audit' => [
        'view',
    ],

    'roles' => [
        'view',
        'create',
        'update',
        'delete',
        'assign',
    ],

    'users' => [
        'view',
        'create',
        'update',
        'disable',
    ],

    'settings' => [
        'view',
        'update',
    ],

    'workflows' => [
        'view',
        'create',
        'update',
        'delete',
    ],

    'departments' => [
        'view',
        'create',
        'update',
        'delete',
    ],

];
