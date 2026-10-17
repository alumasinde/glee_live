<?php

/*
|--------------------------------------------------------------------------
| Sidebar Menu
|--------------------------------------------------------------------------
|
| Each entry is rendered only when the user holds `permission`.
| match: "exact" highlights only on the route itself,
|        "prefix" highlights on the route and any child path.
|
*/

return [

    [
        'route'      => '/dashboard',
        'label'      => 'Dashboard',
        'icon'       => 'fa-solid fa-gauge-high',
        'permission' => 'dashboard.access',
        'match'      => 'exact',
    ],

    [
        'route'      => '/gatepasses',
        'label'      => 'Gatepasses',
        'icon'       => 'fa-solid fa-file-signature',
        'permission' => 'gatepass.view',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/visitors',
        'label'      => 'Visitors',
        'icon'       => 'fa-solid fa-user',
        'permission' => 'visitors.view',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/visits',
        'label'      => 'Visits',
        'icon'       => 'fa-solid fa-right-to-bracket',
        'permission' => 'visits.view',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/approvals',
        'label'      => 'Approvals',
        'icon'       => 'fa-solid fa-circle-check',
        'permission' => 'approval.view',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/reports',
        'label'      => 'Reports',
        'icon'       => 'fa-solid fa-chart-column',
        'permission' => 'reports.view',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/roles',
        'label'      => 'Roles',
        'icon'       => 'fa-solid fa-shield-halved',
        'permission' => 'roles.view',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/settings',
        'label'      => 'Settings',
        'icon'       => 'fa-solid fa-gear',
        'permission' => 'settings.view',
        'match'      => 'prefix',
    ],

];
