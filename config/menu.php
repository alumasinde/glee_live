<?php

/*
|--------------------------------------------------------------------------
| Sidebar Menu
|--------------------------------------------------------------------------
|
| Each entry is rendered only when the user holds `permission` and the
| tenant's module entitlement is enabled (see ModuleEntitlementService).
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
        'module'     => 'dashboard',
        'match'      => 'exact',
    ],

    [
        'route'      => '/gatepasses',
        'label'      => 'Gatepasses',
        'icon'       => 'fa-solid fa-file-signature',
        'permission' => 'gatepass.view',
        'module'     => 'gatepass',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/visitors',
        'label'      => 'Visitors',
        'icon'       => 'fa-solid fa-user',
        'permission' => 'visitors.view',
        'module'     => 'visitors',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/visits',
        'label'      => 'Visits',
        'icon'       => 'fa-solid fa-right-to-bracket',
        'permission' => 'visits.view',
        'module'     => 'visits',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/badges',
        'label'      => 'Badges',
        'icon'       => 'fa-solid fa-id-card-clip',
        'permission' => 'badges.view',
        'module'     => 'badges',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/approvals',
        'label'      => 'Approvals',
        'icon'       => 'fa-solid fa-circle-check',
        'permission' => 'approval.view',
        'module'     => 'approval',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/notifications',
        'label'      => 'Notifications',
        'icon'       => 'fa-solid fa-bell',
        'permission' => 'notifications.view',
        'module'     => 'notifications',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/reports',
        'label'      => 'Reports',
        'icon'       => 'fa-solid fa-chart-column',
        'permission' => 'reports.view',
        'module'     => 'reports',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/audit',
        'label'      => 'Audit trail',
        'icon'       => 'fa-solid fa-clock-rotate-left',
        'permission' => 'audit.view',
        'module'     => 'audit',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/roles',
        'label'      => 'Roles',
        'icon'       => 'fa-solid fa-shield-halved',
        'permission' => 'roles.view',
        'module'     => 'roles',
        'match'      => 'prefix',
    ],

    [
        'route'      => '/settings',
        'label'      => 'Settings',
        'icon'       => 'fa-solid fa-gear',
        'permission' => 'settings.view',
        'module'     => 'settings',
        'match'      => 'prefix',
    ],

];
