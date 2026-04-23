<?php

use App\Core\Router;
use App\Middleware\AuthMiddleware;

use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\LogoutController;
use App\Modules\Auth\Controllers\PasswordController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Gatepass\Controllers\GatepassController;
use App\Modules\Gatepass\Controllers\GateScanController;
use App\Modules\Visitors\Controllers\VisitorController;
use App\Modules\Visits\Controllers\VisitController;
use App\Modules\Badges\Controllers\BadgeController;
use App\Modules\Settings\Controllers\SettingsController;
use App\Modules\Settings\Controllers\CompanySettingController;
use App\Modules\Settings\Controllers\UserManagementController;
use App\Modules\Reports\Controllers\ReportController;
use App\Modules\Settings\Controllers\GatepassSettingController;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\Roles\Controllers\UserRoleController;
use App\Modules\Approval\Controllers\ApprovalController;
use App\Modules\Settings\Controllers\WorkflowController;
use App\Modules\Settings\Controllers\GatepassTypeController;
use App\Modules\Settings\Controllers\DepartmentController;



$router = new Router();
$auth = [AuthMiddleware::class];

//Guest Routes
$router->get('/', [LoginController::class, 'index']);
$router->get('/login', [LoginController::class, 'index']);
$router->post('/login', [LoginController::class, 'store']);

$router->get('/forgot-password', [PasswordController::class, 'forgot']);
$router->post('/forgot-password', [PasswordController::class, 'sendReset']);

$router->get('/reset-password', [PasswordController::class, 'resetForm']);
$router->post('/reset-password', [PasswordController::class, 'reset']);

//Dashboard Routes

$router->get('/dashboard', [DashboardController::class, 'index'], $auth);
$router->get('/dashboard/charts', [DashboardController::class, 'charts'], $auth);


//Gatepasses
$router->get('/gatepasses', [GatepassController::class, 'index'], $auth);
$router->get('/gatepasses/create', [GatepassController::class, 'create'], $auth);
$router->post('/gatepasses', [GatepassController::class, 'store'], $auth);

$router->get('/gatepasses/{id}/edit', [GatepassController::class, 'edit'], $auth);
$router->post('/gatepasses/{id}/update', [GatepassController::class, 'update'], $auth);
$router->post('/gatepasses/{id}/delete', [GatepassController::class, 'delete'], $auth);
$router->get('/gatepasses/scan', [GateScanController::class, 'index'], $auth);
$router->post('/gatepasses/scan', [GateScanController::class, 'process'], $auth);

// ==============================
// APPROVAL ROUTES
// ==============================

$router->get('/approvals', [ApprovalController::class, 'index'], $auth);
$router->get('/approvals/{id}', [ApprovalController::class, 'show'], $auth);

$router->get('/approvals/{id}/approve', [ApprovalController::class, 'approve'], $auth);
$router->post('/approvals/{id}/approve', [ApprovalController::class, 'approve'], $auth);

$router->get('/approvals/{id}/reject', [ApprovalController::class, 'reject'], $auth);
$router->post('/approvals/{id}/reject', [ApprovalController::class, 'reject'], $auth);

/*
|--------------------------------------------------------------------------
| Visitors Module
|--------------------------------------------------------------------------
*/

$router->get('/visitors', [VisitorController::class, 'index'], $auth);
$router->get('/visitors/create', [VisitorController::class, 'create'], $auth);
$router->post('/visitors', [VisitorController::class, 'store'], $auth);
$router->post('/visitors/{id}/blacklist', [VisitorController::class, 'blacklist'], $auth);
$router->post('/visitors/{id}/unblacklist', [VisitorController::class, 'unblacklist'], $auth);


/*
|--------------------------------------------------------------------------
| Visits Module
|--------------------------------------------------------------------------
*/

$router->get('/visits', [VisitController::class, 'index'], $auth);
$router->get('/visits/create', [VisitController::class, 'create'], $auth);
$router->post('/visits', [VisitController::class, 'store'], $auth);
$router->get('/visitors/{id}', [VisitorController::class, 'view'], $auth);
$router->get('/visitors/{id}/edit', [VisitorController::class, 'edit'], $auth);
$router->post('/visitors/{id}/update', [VisitorController::class, 'update'], $auth);
$router->post('/visits/{id}/checkin', [VisitController::class, 'checkIn'], $auth);
$router->post('/visits/{id}/checkout', [VisitController::class, 'checkOut'], $auth);


/*
|--------------------------------------------------------------------------
| Badges Module
|--------------------------------------------------------------------------
*/

$router->post('/badges/{id}/issue', [BadgeController::class, 'issue'], $auth);
$router->post('/badges/{id}/return', [BadgeController::class, 'return'], $auth);


//Roles
$router->get('/roles', [RoleController::class, 'index'], $auth);
$router->get('/roles/create', [RoleController::class, 'create'], $auth);
$router->post('/roles', [RoleController::class, 'store'], $auth);
$router->get('/roles/{id}/edit', [RoleController::class, 'edit'], $auth);
$router->post('/roles/{id}', [RoleController::class, 'update'], $auth);
$router->post('/roles/{id}/delete', [RoleController::class, 'delete'], $auth);

$router->get('/roles/{id}/permissions', [RoleController::class, 'permissions'], $auth);
$router->post('/roles/{id}/permissions', [RoleController::class, 'updatePermissions'], $auth);

$router->get('/users/{id}/roles', [UserRoleController::class, 'index'], $auth);
$router->post('/users/{id}/roles', [UserRoleController::class, 'update'], $auth);

//Settings

$router->get('/settings', [SettingsController::class, 'index'], $auth);

$router->get('/settings/company', [CompanySettingController::class, 'index'], $auth);
$router->post('/settings/company', [CompanySettingController::class, 'update'], $auth);

$router->get('/settings/gatepass-numbering', [GatepassSettingController::class, 'index'], $auth);
$router->post('/settings/gatepass-numbering', [GatepassSettingController::class, 'update'], $auth);

$router->get('/settings/users', [UserManagementController::class, 'index'], $auth);
$router->get('/settings/users/create', [UserManagementController::class, 'create'], $auth);
$router->post('/settings/users', [UserManagementController::class, 'store'], $auth);

$router->get('/settings/users/{id}/edit', [UserManagementController::class, 'edit'], $auth);

$router->post('/settings/users/{id}', [UserManagementController::class, 'update'], $auth);

$router->get('/settings/gatepass-types', [GatepassTypeController::class, 'index'], $auth);
$router->post('/settings/gatepass-types/update', [GatepassTypeController::class, 'update'], $auth);
$router->get(
    '/settings/departments',
    [DepartmentController::class, 'index'],
    $auth
);

$router->post(
    '/settings/departments/create',
    [DepartmentController::class, 'store'],
    $auth
);

$router->post(
    '/settings/departments/update',
    [DepartmentController::class, 'update'],
    $auth
);

$router->post(
    '/settings/departments/deactivate',
    [DepartmentController::class, 'deactivate'],
    $auth
);

// ==============================
// WORKFLOW SETTINGS
// ==============================

$router->get('/settings/workflows', [WorkflowController::class, 'index'], $auth);

$router->get('/settings/workflows/create', [WorkflowController::class, 'create'], $auth);
$router->post('/settings/workflows', [WorkflowController::class, 'store'], $auth);

$router->get('/settings/workflows/{id}/edit', [WorkflowController::class, 'edit'], $auth);
$router->post('/settings/workflows/{id}', [WorkflowController::class, 'update'], $auth);

$router->get('/settings/workflows/{id}/steps',[WorkflowController::class, 'steps'], $auth);
$router->post('/settings/workflows/{id}/steps',[WorkflowController::class, 'storeStep'], $auth);

$router->get('/settings/workflows/{id}/assign',[WorkflowController::class, 'assign'], $auth);
$router->post('/settings/workflows/{id}/assign', [WorkflowController::class, 'storeAssignment'], $auth);

$router->get('/settings/users/profile', [UserManagementController::class, 'profile'] , $auth);
$router->post('/settings/users/profile', [UserManagementController::class, 'updateProfile'] , $auth);

/*
|--------------------------------------------------------------------------
| Reports Module
|--------------------------------------------------------------------------
*/

$router->get('/reports', [ReportController::class, 'index'], $auth);

$router->get('/reports/gatepasses', [ReportController::class, 'gatepasses'], $auth);
$router->get('/reports/visitors', [ReportController::class, 'visitors'], $auth);
$router->get('/reports/visits', [ReportController::class, 'visits'], $auth);
$router->get('/reports/audit-logs', [ReportController::class, 'auditLogs'], $auth);

$router->get('/reports/gatepasses/export', [ReportController::class, 'exportGatepasses'], $auth);
$router->get('/reports/visitors/export', [ReportController::class, 'exportVisitors'], $auth);
$router->get('/reports/visits/export', [ReportController::class, 'exportVisits'], $auth);
$router->get('/reports/audit-logs/export', [ReportController::class, 'exportAuditLogs'], $auth);

// Gatepass Check-In / Check-Out
$router->post('/gatepasses/{id}/checkin', [GatepassController::class, 'checkIn'], $auth);
$router->post('/gatepasses/{id}/checkout', [GatepassController::class, 'checkOut'], $auth);

//Dynamic Gatepass Routes (KEEP LAST)

$router->get('/gatepasses/{id}', [GatepassController::class, 'show'], $auth);
$router->get('/gatepasses/{id}/edit', [GatepassController::class, 'edit'], $auth);
$router->post('/gatepasses/{id}', [GatepassController::class, 'update'], $auth);
$router->post('/gatepasses/{id}/delete', [GatepassController::class, 'delete'], $auth);

//Logout Routes
$router->post(
    path: '/logout',
    handler: [LogoutController::class, '__invoke'],
    middleware: $auth // optional auth middleware
);
return $router;