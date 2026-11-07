<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Permission;
use App\Core\PlatformPolicy;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Platform\Services\PlatformImpersonationService;
use App\Modules\Platform\Services\TenantProvisioningService;
use App\Modules\Platform\Services\TenantStatusService;

final class PlatformTenantStatusTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        PlatformPolicy::flush();
        Permission::flush();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        PlatformPolicy::flush();
        Permission::flush();

        parent::tearDown();
    }

    public function testSuspensionRevokesSessionsEndsSupportAndIsAudited(): void
    {
        $tenantId = $this->provisionTenant();
        $platformId = $this->platformUser();
        $userId = $this->tenantUserId($tenantId);

        $_SESSION['platform_user'] = [
            'id'    => $platformId,
            'name'  => 'Platform Ops',
            'email' => 'ops@platform.test',
        ];

        $impersonation = new PlatformImpersonationService($this->db);
        $started = $impersonation->start($platformId, $tenantId, $userId, 'Ticket 900');

        $this->assertTrue($started['ok'], $started['error'] ?? '');
        $impersonationId = (int) $started['impersonation_id'];

        $result = (new TenantStatusService($this->db))
            ->suspend($tenantId, $platformId, 'Non-payment');

        $this->assertTrue($result['changed']);
        $this->assertSame(1, $result['revoked_users']);
        $this->assertSame(1, $result['ended_support_sessions']);

        $tenantActive = (int) $this->db->query(
            "SELECT is_active FROM tenants WHERE id = {$tenantId}"
        )->fetchColumn();

        $this->assertSame(0, $tenantActive, 'the tenant is inactive');

        $stamp = $this->db->query(
            "SELECT tokens_invalid_before FROM users WHERE id = {$userId}"
        )->fetchColumn();

        $this->assertNotNull($stamp, 'every tenant session is invalidated');

        $row = $this->db->query(
            "SELECT ended_at, end_reason FROM platform_impersonations WHERE id = {$impersonationId}"
        )->fetch();

        $this->assertNotNull($row['ended_at']);
        $this->assertSame('tenant_suspended', $row['end_reason']);

        $this->assertSame(1, (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_audit_logs
              WHERE action = 'tenant.suspended' AND subject_type = 'tenant' AND subject_id = {$tenantId}"
        )->fetchColumn());

        // The per-request guard now clears the tenant identity and keeps the
        // platform identity that owns the session.
        $check = $impersonation->validate();

        $this->assertFalse($check['ok']);
        $this->assertArrayNotHasKey('user', $_SESSION);
        $this->assertArrayNotHasKey(PlatformImpersonationService::SESSION_KEY, $_SESSION);
        $this->assertSame($platformId, (int) $_SESSION['platform_user']['id']);
    }

    public function testSuspendedTenantRejectsLoginAndExistingSessions(): void
    {
        $tenantId = $this->provisionTenant();
        $userId = $this->tenantUserId($tenantId);

        (new TenantStatusService($this->db))->suspend($tenantId);

        $login = (new AuthService())->login(
            $tenantId,
            'admin@acme.test',
            'Str0ng!Passw0rd9'
        );

        $this->assertSame(AuthService::STATUS_FAILED, $login['status']);

        // A session issued before the suspension, with a valid auth stamp.
        Auth::forget();
        $_SESSION['user'] = ['id' => $userId, 'tenant_id' => $tenantId];
        $_SESSION['auth_at'] = date('Y-m-d H:i:s', time() - 3600);

        $this->assertFalse(Auth::sessionIsValid());

        // Legacy session without an auth stamp: tenant state still decides.
        Auth::forget();
        $_SESSION['user'] = ['id' => $userId, 'tenant_id' => $tenantId];

        $this->assertFalse(Auth::sessionIsValid());
    }

    public function testReactivationRestoresSignInAndIsAudited(): void
    {
        $tenantId = $this->provisionTenant();
        $service = new TenantStatusService($this->db);

        $service->suspend($tenantId, null, 'Review');
        $result = $service->reactivate($tenantId, null, 'Review complete');

        $this->assertTrue($result['changed']);
        $this->assertSame(1, (int) $this->db->query(
            "SELECT is_active FROM tenants WHERE id = {$tenantId}"
        )->fetchColumn());

        $this->assertSame(1, (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_audit_logs
              WHERE action = 'tenant.reactivated' AND subject_type = 'tenant' AND subject_id = {$tenantId}"
        )->fetchColumn());

        $login = (new AuthService())->login(
            $tenantId,
            'admin@acme.test',
            'Str0ng!Passw0rd9'
        );

        $this->assertSame(AuthService::STATUS_LOGIN, $login['status']);
    }

    private function provisionTenant(): int
    {
        return (new TenantProvisioningService($this->db))->create([
            'name'             => 'Acme Status Ltd',
            'code'             => 'acme-status',
            'email'            => 'hello@acme.test',
            'phone'            => '+254700000001',
            'country'          => 'Kenya',
            'admin_first_name' => 'Acme',
            'admin_last_name'  => 'Admin',
            'admin_email'      => 'admin@acme.test',
            'admin_password'   => 'Str0ng!Passw0rd9',
        ]);
    }

    private function platformUser(): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO platform_users (name, email, password_hash, is_superadmin)
             VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([
            'Platform Ops',
            'status' . bin2hex(random_bytes(4)) . '@platform.test',
            password_hash('Platform!2026x', PASSWORD_BCRYPT, ['cost' => 4]),
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function tenantUserId(int $tenantId): int
    {
        return (int) $this->db->query(
            "SELECT id FROM users WHERE tenant_id = {$tenantId} AND email = 'admin@acme.test'"
        )->fetchColumn();
    }
}
