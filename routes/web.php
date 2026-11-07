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
use App\Modules\Auth\Controllers\TwoFactorController;
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
use App\Modules\Approval\Controllers\DelegationController;
use App\Modules\Audit\Controllers\AuditController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\Settings\Controllers\WorkflowController;
use App\Modules\Settings\Controllers\GatepassTypeController;
use App\Modules\Settings\Controllers\DepartmentController;
use App\Modules\Settings\Controllers\GateSettingController;
use App\Modules\Gatepass\Controllers\DeviceScanController;
use App\Middleware\FeatureMiddleware;
use App\Middleware\ImpersonationGuardMiddleware;
use App\Middleware\ModuleMiddleware;
use App\Middleware\PlatformAuthMiddleware;
use App\Modules\Platform\Controllers\PlatformAuditController;
use App\Modules\Platform\Controllers\PlatformAuthController;
use App\Modules\Platform\Controllers\PlatformDashboardController;
use App\Modules\Platform\Controllers\PlatformSettingsController;
use App\Modules\Platform\Controllers\PlatformTenantController;

$router = new Router();

// Apply security headers to all routes
$router->setGlobalMiddleware([
    SecurityHeadersMiddleware::class,
    FeatureMiddleware::class,
    ModuleMiddleware::class,
    ImpersonationGuardMiddleware::class,
]);

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

$perm     = fn(string $p): array => [AuthMiddleware::class, PermissionMiddleware::class . ':' . $p];
$permCsrf = fn(string $p): array => [AuthMiddleware::class, PermissionMiddleware::class . ':' . $p, CSRFMiddleware::class];

//Guest Routes
$guestCsrf = [CSRFMiddleware::class];

// Rate limiting for authentication endpoints (reads from config/security.php).
// The middleware key is matched against the config array literally, so these use
// underscores — "password-reset" silently falls back to the 5/15 defaults.
$loginRateLimit = [CSRFMiddleware::class, RateLimitMiddleware::class . ':login'];
$resetRateLimit = [CSRFMiddleware::class, RateLimitMiddleware::class . ':password_reset'];
$mfaRateLimit   = [CSRFMiddleware::class, RateLimitMiddleware::class . ':mfa'];
$scanRateLimit  = [AuthMiddleware::class, RateLimitMiddleware::class . ':scan'];

$router->get('/', [LoginController::class, 'index']);
$router->get('/login', [LoginController::class, 'index']);
$router->post('/login', [LoginController::class, 'store'], $loginRateLimit);

// Two-factor challenge: only reachable with the parked, password-verified
// session, and rate limited on top of the account lockout.
$router->get('/login/two-factor', [LoginController::class, 'twoFactorForm']);
$router->post('/login/two-factor', [LoginController::class, 'twoFactor'], $mfaRateLimit);

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
// Rate limited by IP: a cloned key hammering the endpoint cannot outrun this.
$router->post('/gate-scan', [DeviceScanController::class, 'scan'], [RateLimitMiddleware::class . ':scan']);

//Dashboard Routes
$router->get('/dashboard', [DashboardController::class, 'index'], $perm('dashboard.access'));
$router->get('/dashboard/charts', [DashboardController::class, 'charts'], $perm('dashboard.access'));

//Gatepasses
$router->get('/gatepasses', [GatepassController::class, 'index'], $perm('gatepass.view'));
$router->get('/gatepasses/create', [GatepassController::class, 'create'], $perm('gatepass.create'));
$router->post('/gatepasses', [GatepassController::class, 'store'], $permCsrf('gatepass.create'));

$router->get('/gatepasses/scan', [GateScanController::class, 'index'], $perm('gatepass.scan'));
$router->post('/gatepasses/scan', [GateScanController::class, 'process'], [
    AuthMiddleware::class,
    PermissionMiddleware::class . ':gatepass.scan',
    CSRFMiddleware::class,
    RateLimitMiddleware::class . ':scan',
]);

// ==============================
// APPROVAL ROUTES
// ==============================

// Delegation is a literal segment and must be registered before /approvals/{id}.
$router->get('/approvals/delegation', [DelegationController::class, 'index'], $perm('approval.delegate'));
$router->post('/approvals/delegation', [DelegationController::class, 'store'], $permCsrf('approval.delegate'));
$router->post('/approvals/delegation/{id}/delete', [DelegationController::class, 'destroy'], $permCsrf('approval.delegate'));

$router->get('/approvals', [ApprovalController::class, 'index'], $perm('approval.view'));
$router->get('/approvals/{id}', [ApprovalController::class, 'show'], $perm('approval.view'));

$router->get('/approvals/{id}/approve', [ApprovalController::class, 'approveForm'], $perm('approval.approve'));
$router->post('/approvals/{id}/approve', [ApprovalController::class, 'approve'], $permCsrf('approval.approve'));

$router->get('/approvals/{id}/reject', [ApprovalController::class, 'rejectForm'], $perm('approval.reject'));
$router->post('/approvals/{id}/reject', [ApprovalController::class, 'reject'], $permCsrf('approval.reject'));

$router->get('/approvals/{id}/reassign', [ApprovalController::class, 'reassignForm'], $perm('approval.reassign'));
$router->post('/approvals/{id}/reassign', [ApprovalController::class, 'reassign'], $permCsrf('approval.reassign'));

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
$router->post('/visitors/{id}/unblacklist', [VisitorController::class, 'unblacklist'], $permCsrf('visitors.unblacklist'));

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

// Register /badges/create before any /badges/{id} pattern: {id} swallows "create".
$router->get('/badges', [BadgeController::class, 'index'], $perm('badges.view'));
$router->get('/badges/create', [BadgeController::class, 'create'], $perm('badges.issue'));
$router->post('/badges', [BadgeController::class, 'store'], $permCsrf('badges.issue'));
$router->get('/badges/{id}/issue', [BadgeController::class, 'issueForm'], $perm('badges.issue'));

$router->post('/badges/{id}/issue', [BadgeController::class, 'issue'], $permCsrf('badges.issue'));
$router->post('/badges/{id}/return', [BadgeController::class, 'return'], $permCsrf('badges.return'));

/*
|--------------------------------------------------------------------------
| Audit Trail (module screens; export stays under /reports)
|--------------------------------------------------------------------------
*/

$router->get('/audit', [AuditController::class, 'index'], $perm('audit.view'));
$router->get('/audit/{id}', [AuditController::class, 'show'], $perm('audit.view'));

/*
|--------------------------------------------------------------------------
| Notifications (in-app feed, per-user channels, push devices)
|--------------------------------------------------------------------------
*/

$router->get('/notifications', [NotificationController::class, 'index'], $perm('notifications.view'));
$router->post('/notifications/read-all', [NotificationController::class, 'readAll'], $permCsrf('notifications.view'));

// Preferences is literal and comes before /notifications/{id}/…
$router->get('/notifications/preferences', [NotificationController::class, 'preferences'], $perm('notifications.view'));
$router->post('/notifications/preferences', [NotificationController::class, 'savePreferences'], $permCsrf('notifications.update'));
$router->post('/notifications/devices', [NotificationController::class, 'registerDevice'], $permCsrf('notifications.update'));
$router->post('/notifications/devices/{id}/delete', [NotificationController::class, 'deleteDevice'], $permCsrf('notifications.update'));

$router->get('/notifications/{id}/read', [NotificationController::class, 'read'], $perm('notifications.view'));
$router->post('/notifications/{id}/read', [NotificationController::class, 'read'], $permCsrf('notifications.view'));

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

$router->get('/settings/email', [EmailSettingController::class, 'index'], $perm('email.view'));
$router->post('/settings/email', [EmailSettingController::class, 'update'], $permCsrf('email.update'));
$router->post('/settings/email/test', [EmailSettingController::class, 'test'], $permCsrf('email.test_send'));

$router->get('/settings/gates', [GateSettingController::class, 'index'], $perm('gates.view'));
$router->post('/settings/gates', [GateSettingController::class, 'store'], $permCsrf('gates.create'));
$router->post('/settings/gates/update', [GateSettingController::class, 'update'], $permCsrf('gates.update'));
$router->post('/settings/gates/rotate', [GateSettingController::class, 'rotate'], $permCsrf('gates.rotate'));
$router->post('/settings/gates/delete', [GateSettingController::class, 'delete'], $permCsrf('gates.delete'));

$router->get('/settings/gatepass-types', [GatepassTypeController::class, 'index'], $perm('gatepass_types.view'));
$router->post('/settings/gatepass-types/update', [GatepassTypeController::class, 'update'], $permCsrf('gatepass_types.update'));

$router->get('/settings/users', [UserManagementController::class, 'index'], $perm('users.view'));
$router->get('/settings/users/create', [UserManagementController::class, 'create'], $perm('users.create'));

// Profile is self-service: any authenticated user (registered before /{id} wildcard)
$router->get('/settings/users/profile', [UserManagementController::class, 'profile'], $auth);
$router->post('/settings/users/profile', [UserManagementController::class, 'updateProfile'], $authCsrf);

// Two-factor enrolment is self-service too; the controller applies the
// security.two_factor.require_permission gate rather than a role check.
$router->get('/settings/security/two-factor', [TwoFactorController::class, 'index'], $auth);
$router->post('/settings/security/two-factor', [TwoFactorController::class, 'enable'], $authCsrf);
$router->post('/settings/security/two-factor/disable', [TwoFactorController::class, 'disable'], $authCsrf);

$router->post('/settings/users', [UserManagementController::class, 'store'], $permCsrf('users.create'));
$router->get('/settings/users/{id}/edit', [UserManagementController::class, 'edit'], $perm('users.update'));
$router->post('/settings/users/{id}', [UserManagementController::class, 'update'], $permCsrf('users.update'));
$router->post('/settings/users/{id}/deactivate', [UserManagementController::class, 'deactivate'], $permCsrf('users.disable'));
$router->post('/settings/users/{id}/activate', [UserManagementController::class, 'activate'], $permCsrf('users.disable'));

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
$router->post('/settings/workflows/{id}/steps/{stepId}', [WorkflowController::class, 'updateStep'], $permCsrf('workflows.update'));
$router->post('/settings/workflows/{id}/steps/{stepId}/delete', [WorkflowController::class, 'deleteStep'], $permCsrf('workflows.update'));
$router->get('/settings/workflows/{id}/assign', [WorkflowController::class, 'assign'], $perm('workflows.update'));
$router->post('/settings/workflows/{id}/assign', [WorkflowController::class, 'storeAssignment'], $permCsrf('workflows.update'));

/*
|--------------------------------------------------------------------------
| Reports Module
|--------------------------------------------------------------------------
*/

$router->get('/reports', [ReportController::class, 'index'], $perm('reports.view'));
$router->get('/reports/gatepasses', [ReportController::class, 'gatepasses'], $perm('reports.view_gatepasses'));
$router->get('/reports/visitors', [ReportController::class, 'visitors'], $perm('reports.view_visitors'));
$router->get('/reports/visits', [ReportController::class, 'visits'], $perm('reports.view_visits'));
$router->get('/reports/occupancy', [ReportController::class, 'occupancy'], $perm('reports.view_occupancy'));
$router->get('/reports/scan-activity', [ReportController::class, 'scanActivity'], $perm('reports.view_scans'));
$router->get('/reports/departments', [ReportController::class, 'departments'], $perm('reports.view_departments'));
$router->get('/reports/audit-logs', [ReportController::class, 'auditLogs'], $perm('audit.view'));

$router->get('/reports/gatepasses/export', [ReportController::class, 'exportGatepasses'], $perm('reports.export_gatepasses'));
$router->get('/reports/visitors/export', [ReportController::class, 'exportVisitors'], $perm('reports.export_visitors'));
$router->get('/reports/visits/export', [ReportController::class, 'exportVisits'], $perm('reports.export_visits'));
$router->get('/reports/occupancy/export', [ReportController::class, 'exportOccupancy'], $perm('reports.export_occupancy'));
$router->get('/reports/scan-activity/export', [ReportController::class, 'exportScanActivity'], $perm('reports.export_scans'));
$router->get('/reports/departments/export', [ReportController::class, 'exportDepartments'], $perm('reports.export_departments'));
$router->get('/reports/audit-logs/export', [ReportController::class, 'exportAuditLogs'], $perm('audit.export'));

// Gatepass Check-In / Check-Out
$router->post('/gatepasses/{id}/checkin', [GatepassController::class, 'checkIn'], $permCsrf('gatepass.checkin'));
$router->post('/gatepasses/{id}/checkout', [GatepassController::class, 'checkOut'], $permCsrf('gatepass.checkout'));

//Dynamic Gatepass Routes (KEEP LAST)
$router->get('/gatepasses/{id}', [GatepassController::class, 'show'], $perm('gatepass.view'));
$router->get('/gatepasses/{id}/edit', [GatepassController::class, 'edit'], $perm('gatepass.update'));
$router->post('/gatepasses/{id}', [GatepassController::class, 'update'], $permCsrf('gatepass.update'));
$router->post('/gatepasses/{id}/update', [GatepassController::class, 'update'], $permCsrf('gatepass.update'));
$router->post('/gatepasses/{id}/delete', [GatepassController::class, 'delete'], $permCsrf('gatepass.delete'));

/*
|--------------------------------------------------------------------------
| Platform (provider) console
|--------------------------------------------------------------------------
|
| Separate identity plane: PlatformAuthMiddleware only accepts the platform
| session key, so a tenant session can never open these screens. Module and
| feature gating intentionally do not apply to /platform.
|
*/

$platformAuth     = [PlatformAuthMiddleware::class];
$platformAuthCsrf = [PlatformAuthMiddleware::class, CSRFMiddleware::class];

$router->get('/platform/login', [PlatformAuthController::class, 'loginForm'], [CSRFMiddleware::class]);
$router->post('/platform/login', [PlatformAuthController::class, 'login'], [CSRFMiddleware::class, RateLimitMiddleware::class . ':login']);
$router->post('/platform/logout', [PlatformAuthController::class, 'logout'], $platformAuthCsrf);

$router->get('/platform', [PlatformDashboardController::class, 'index'], $platformAuth);
$router->get('/platform/features', [PlatformSettingsController::class, 'index'], $platformAuth);
$router->post('/platform/features', [PlatformSettingsController::class, 'update'], $platformAuthCsrf);
$router->get('/platform/audit', [PlatformAuditController::class, 'index'], $platformAuth);

// /platform/tenants/create must be registered before the {id} wildcard.
$router->get('/platform/tenants/create', [PlatformTenantController::class, 'createForm'], $platformAuth);
$router->post('/platform/tenants', [PlatformTenantController::class, 'store'], $platformAuthCsrf);

$router->get('/platform/tenants/{id}', [PlatformTenantController::class, 'show'], $platformAuth);
$router->post('/platform/tenants/{id}/modules', [PlatformTenantController::class, 'updateModules'], $platformAuthCsrf);
$router->post('/platform/tenants/{id}/suspend', [PlatformTenantController::class, 'suspend'], $platformAuthCsrf);
$router->post('/platform/tenants/{id}/reactivate', [PlatformTenantController::class, 'reactivate'], $platformAuthCsrf);
$router->post('/platform/tenants/{id}/impersonate', [PlatformTenantController::class, 'impersonate'], $platformAuthCsrf);
$router->post('/platform/impersonation/stop', [PlatformTenantController::class, 'stopImpersonation'], $platformAuthCsrf);

//Logout Routes
$router->post(
    path: '/logout',
    handler: [LogoutController::class, '__invoke'],
    middleware: $authCsrf
);

return $router;
