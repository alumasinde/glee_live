<?php

use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CSRFMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\LogoutController;
use App\Modules\Auth\Controllers\PasswordController;
use App\Modules\Dashboard\Controllers\DashboardController;
use App\Modules\Gatepass\Controllers\GatepassController;
use App\Modules\Gatepass\Controllers\GateScanController;
use App\Modules\Visitors\Controllers\VisitorController;
use App\Modules\Visits\Controllers\VisitController;
use App\Modules\Visits\Controllers\PreRegistrationController;
use App\Modules\Badges\Controllers\BadgeController;
use App\Modules\Settings\Controllers\SettingsController;
use App\Modules\Settings\Controllers\CompanySettingController;
use App\Modules\Settings\Controllers\UserManagementController;
use App\Modules\Reports\Controllers\ReportController;
use App\Modules\Settings\Controllers\GatepassSettingController;
use App\Modules\Settings\Controllers\EmailSettingController;
use App\Modules\Roles\Controllers\RoleController;
use App\Modules\Roles\Controllers\UserRoleController;
use App\Modules\Approval\Controllers\ApprovalController;
use App\Modules\Settings\Controllers\WorkflowController;
use App\Modules\Settings\Controllers\GatepassTypeController;
use App\Modules\Settings\Controllers\DepartmentController;
use App\Modules\Settings\Controllers\GateSettingController;
use App\Modules\Gatepass\Controllers\DeviceScanController;

$router = new Router();

// Apply security headers to all routes
$router->setGlobalMiddleware([SecurityHeadersMiddleware::class]);

/*
|--------------------------------------------------------------------------
| Middleware presets
|--------------------------------------------------------------------------
|
| perm('x')    => auth + PermissionMiddleware:x
| permCsrf(x)  => auth + PermissionMiddleware:x + CSRF
|
*/

$auth     = [AuthMiddleware::class];
$authCsrf = [AuthMiddleware::class, CSRFMiddleware::class];

$perm     = fn (string $p): array => [AuthMiddleware::class, PermissionMiddleware::class . ':' . $p];
$permCsrf = fn (string $p): array => [AuthMiddleware::class, PermissionMiddleware::class . ':' . $p, CSRFMiddleware::class];

//Guest Routes
$guestCsrf = [CSRFMiddleware::class];

// Rate limiting for authentication endpoints (reads from config/security.php)
$loginRateLimit = [CSRFMiddleware::class, RateLimitMiddleware::class . ':login'];
$resetRateLimit = [CSRFMiddleware::class, RateLimitMiddleware::class . ':password-reset'];

$router->get('/', [LoginController::class, 'index']);
$router->get('/login', [LoginController::class, 'index']);
$router->post('/login', [LoginController::class, 'store'], $loginRateLimit);

$router->get('/forgot-password', [PasswordController::class, 'forgot']);
$router->post('/forgot-password', [PasswordController::class, 'sendReset'], $resetRateLimit);

$router->get('/reset-password', [PasswordController::class, 'resetForm']);
$router->post('/reset-password', [PasswordController::class, 'reset'], $resetRateLimit);

// Public visitor pre-registration + token-addressable digital QR pass
$router->get('/pre-register/{code}', [PreRegistrationController::class, 'form']);
$router->post('/pre-register/{code}', [PreRegistrationController::class, 'submit'], $guestCsrf);
$router->get('/pass/{token}', [PreRegistrationController::class, 'pass']);

// Gate device scanning (authenticated per-gate by device key, not by session).
// Path avoids /api/* which the local vhost proxies to a separate backend.
$router->post('/gate-scan', [DeviceScanController::class, 'scan']);

//Dashboard Routes
$router->get('/dashboard', [DashboardController::class, 'index'], $perm('dashboard.access'));
$router->get('/dashboard/charts', [DashboardController::class, 'charts'], $perm('dashboard.access'));

//Gatepasses
$router->get('/gatepasses', [GatepassController::class, 'index'], $perm('gatepass.view'));
$router->get('/gatepasses/create', [GatepassController::class, 'create'], $perm('gatepass.create'));
$router->post('/gatepasses', [GatepassController::class, 'store'], $permCsrf('gatepass.create'));

$router->get('/gatepasses/scan', [GateScanController::class, 'index'], $perm('gatepass.scan'));
$router->post('/gatepasses/scan', [GateScanController::class, 'process'], $permCsrf('gatepass.scan'));

// ==============================
// APPROVAL ROUTES
// ==============================

$router->get('/approvals', [ApprovalController::class, 'index'], $perm('approval.view'));
$router->get('/approvals/{id}', [ApprovalController::class, 'show'], $perm('approval.view'));

$router->get('/approvals/{id}/approve', [ApprovalController::class, 'approveForm'], $perm('approval.approve'));
$router->post('/approvals/{id}/approve', [ApprovalController::class, 'approve'], $permCsrf('approval.approve'));

$router->get('/approvals/{id}/reject', [ApprovalController::class, 'rejectForm'], $perm('approval.reject'));
$router->post('/approvals/{id}/reject', [ApprovalController::class, 'reject'], $permCsrf('approval.reject'));

/*
|--------------------------------------------------------------------------
| Visitors Module
|--------------------------------------------------------------------------
*/

$router->get('/visitors', [VisitorController::class, 'index'], $perm('visitors.view'));
$router->get('/visitors/create', [VisitorController::class, 'create'], $perm('visitors.create'));
$router->post('/visitors', [VisitorController::class, 'store'], $permCsrf('visitors.create'));
$router->get('/visitors/{id}', [VisitorController::class, 'view'], $perm('visitors.view'));
$router->get('/visitors/{id}/edit', [VisitorController::class, 'edit'], $perm('visitors.update'));
$router->post('/visitors/{id}/update', [VisitorController::class, 'update'], $permCsrf('visitors.update'));
$router->post('/visitors/{id}/blacklist', [VisitorController::class, 'blacklist'], $permCsrf('visitors.blacklist'));
$router->post('/visitors/{id}/unblacklist', [VisitorController::class, 'unblacklist'], $permCsrf('visitors.blacklist'));

/*
|--------------------------------------------------------------------------
| Visits Module
|--------------------------------------------------------------------------
*/

$router->get('/visits', [VisitController::class, 'index'], $perm('visits.view'));
$router->get('/visits/create', [VisitController::class, 'create'], $perm('visits.create'));
$router->post('/visits', [VisitController::class, 'store'], $permCsrf('visits.create'));
$router->post('/visits/{id}/checkin', [VisitController::class, 'checkIn'], $permCsrf('visits.check_in'));
$router->post('/visits/{id}/checkout', [VisitController::class, 'checkOut'], $permCsrf('visits.check_out'));

/*
|--------------------------------------------------------------------------
| Badges Module
|--------------------------------------------------------------------------
*/

$router->post('/badges/{id}/issue', [BadgeController::class, 'issue'], $permCsrf('visitors.issue_badge'));
$router->post('/badges/{id}/return', [BadgeController::class, 'return'], $permCsrf('visitors.issue_badge'));

//Roles
$router->get('/roles', [RoleController::class, 'index'], $perm('roles.view'));
$router->get('/roles/create', [RoleController::class, 'create'], $perm('roles.create'));
$router->post('/roles', [RoleController::class, 'store'], $permCsrf('roles.create'));
$router->get('/roles/{id}/edit', [RoleController::class, 'edit'], $perm('roles.update'));
$router->post('/roles/{id}', [RoleController::class, 'update'], $permCsrf('roles.update'));
$router->post('/roles/{id}/delete', [RoleController::class, 'delete'], $permCsrf('roles.delete'));

$router->get('/roles/{id}/permissions', [RoleController::class, 'permissions'], $perm('roles.view'));
$router->post('/roles/{id}/permissions', [RoleController::class, 'updatePermissions'], $permCsrf('roles.assign'));

$router->get('/users/{id}/roles', [UserRoleController::class, 'index'], $perm('roles.view'));
$router->post('/users/{id}/roles', [UserRoleController::class, 'update'], $permCsrf('roles.assign'));

//Settings
$router->get('/settings', [SettingsController::class, 'index'], $perm('settings.view'));

$router->get('/settings/company', [CompanySettingController::class, 'index'], $perm('settings.view'));
$router->post('/settings/company', [CompanySettingController::class, 'update'], $permCsrf('settings.update'));

$router->get('/settings/gatepass-numbering', [GatepassSettingController::class, 'index'], $perm('settings.view'));
$router->post('/settings/gatepass-numbering', [GatepassSettingController::class, 'update'], $permCsrf('settings.update'));

$router->get('/settings/email', [EmailSettingController::class, 'index'], $perm('settings.view'));
$router->post('/settings/email', [EmailSettingController::class, 'update'], $permCsrf('settings.update'));
$router->post('/settings/email/test', [EmailSettingController::class, 'test'], $permCsrf('settings.update'));

$router->get('/settings/gates', [GateSettingController::class, 'index'], $perm('settings.view'));
$router->post('/settings/gates', [GateSettingController::class, 'store'], $permCsrf('settings.update'));
$router->post('/settings/gates/update', [GateSettingController::class, 'update'], $permCsrf('settings.update'));
$router->post('/settings/gates/rotate', [GateSettingController::class, 'rotate'], $permCsrf('settings.update'));
$router->post('/settings/gates/delete', [GateSettingController::class, 'delete'], $permCsrf('settings.update'));

$router->get('/settings/gatepass-types', [GatepassTypeController::class, 'index'], $perm('settings.view'));
$router->post('/settings/gatepass-types/update', [GatepassTypeController::class, 'update'], $permCsrf('settings.update'));

$router->get('/settings/users', [UserManagementController::class, 'index'], $perm('users.view'));
$router->get('/settings/users/create', [UserManagementController::class, 'create'], $perm('users.create'));

// Profile is self-service: any authenticated user (registered before /{id} wildcard)
$router->get('/settings/users/profile', [UserManagementController::class, 'profile'], $auth);
$router->post('/settings/users/profile', [UserManagementController::class, 'updateProfile'], $authCsrf);

$router->post('/settings/users', [UserManagementController::class, 'store'], $permCsrf('users.create'));
$router->get('/settings/users/{id}/edit', [UserManagementController::class, 'edit'], $perm('users.update'));
$router->post('/settings/users/{id}', [UserManagementController::class, 'update'], $permCsrf('users.update'));

$router->get('/settings/departments', [DepartmentController::class, 'index'], $perm('departments.view'));
$router->post('/settings/departments/create', [DepartmentController::class, 'store'], $permCsrf('departments.create'));
$router->post('/settings/departments/update', [DepartmentController::class, 'update'], $permCsrf('departments.update'));
$router->post('/settings/departments/toggle', [DepartmentController::class, 'toggle'], $permCsrf('departments.update'));
$router->post('/settings/departments/delete', [DepartmentController::class, 'delete'], $permCsrf('departments.delete'));

// ==============================
// WORKFLOW SETTINGS
// ==============================

$router->get('/settings/workflows', [WorkflowController::class, 'index'], $perm('workflows.view'));
$router->get('/settings/workflows/create', [WorkflowController::class, 'create'], $perm('workflows.create'));
$router->post('/settings/workflows', [WorkflowController::class, 'store'], $permCsrf('workflows.create'));
$router->get('/settings/workflows/{id}/edit', [WorkflowController::class, 'edit'], $perm('workflows.update'));
$router->post('/settings/workflows/{id}', [WorkflowController::class, 'update'], $permCsrf('workflows.update'));
$router->get('/settings/workflows/{id}/steps', [WorkflowController::class, 'steps'], $perm('workflows.update'));
$router->post('/settings/workflows/{id}/steps', [WorkflowController::class, 'storeStep'], $permCsrf('workflows.update'));
$router->get('/settings/workflows/{id}/assign', [WorkflowController::class, 'assign'], $perm('workflows.update'));
$router->post('/settings/workflows/{id}/assign', [WorkflowController::class, 'storeAssignment'], $permCsrf('workflows.update'));

/*
|--------------------------------------------------------------------------
| Reports Module
|--------------------------------------------------------------------------
*/

$router->get('/reports', [ReportController::class, 'index'], $perm('reports.view'));
$router->get('/reports/gatepasses', [ReportController::class, 'gatepasses'], $perm('reports.view'));
$router->get('/reports/visitors', [ReportController::class, 'visitors'], $perm('reports.view'));
$router->get('/reports/visits', [ReportController::class, 'visits'], $perm('reports.view'));
$router->get('/reports/occupancy', [ReportController::class, 'occupancy'], $perm('reports.view'));
$router->get('/reports/audit-logs', [ReportController::class, 'auditLogs'], $perm('audit.view'));

$router->get('/reports/gatepasses/export', [ReportController::class, 'exportGatepasses'], $perm('reports.export'));
$router->get('/reports/visitors/export', [ReportController::class, 'exportVisitors'], $perm('reports.export'));
$router->get('/reports/visits/export', [ReportController::class, 'exportVisits'], $perm('reports.export'));
$router->get('/reports/audit-logs/export', [ReportController::class, 'exportAuditLogs'], $perm('audit.view'));

// Gatepass Check-In / Check-Out
$router->post('/gatepasses/{id}/checkin', [GatepassController::class, 'checkIn'], $permCsrf('gatepass.checkin'));
$router->post('/gatepasses/{id}/checkout', [GatepassController::class, 'checkOut'], $permCsrf('gatepass.checkout'));

//Dynamic Gatepass Routes (KEEP LAST)
$router->get('/gatepasses/{id}', [GatepassController::class, 'show'], $perm('gatepass.view'));
$router->get('/gatepasses/{id}/edit', [GatepassController::class, 'edit'], $perm('gatepass.update'));
$router->post('/gatepasses/{id}', [GatepassController::class, 'update'], $permCsrf('gatepass.update'));
$router->post('/gatepasses/{id}/update', [GatepassController::class, 'update'], $permCsrf('gatepass.update'));
$router->post('/gatepasses/{id}/delete', [GatepassController::class, 'delete'], $permCsrf('gatepass.delete'));

//Logout Routes
$router->post(
    path: '/logout',
    handler: [LogoutController::class, '__invoke'],
    middleware: $authCsrf
);

return $router;
