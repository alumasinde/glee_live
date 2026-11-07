<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Permission;
use App\Core\PlatformPolicy;
use App\Modules\Platform\Services\PlatformImpersonationService;
use App\Modules\Platform\Services\TenantProvisioningService;
use RuntimeException;

final class PlatformTenantAdminTest extends DatabaseTestCase
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

    public function testProvisioningCreatesTenantAdminRoleAndFirstAdministrator(): void
    {
        $tenantId = (new TenantProvisioningService($this->db))->create($this->tenantInput());

        $tenant = $this->db->query(
            "SELECT id, name, code, email, is_active FROM tenants WHERE id = {$tenantId}"
        )->fetch();

        $this->assertSame('acme-support', $tenant['code']);
        $this->assertSame(1, (int) $tenant['is_active']);

        $roleId = (int) $this->db->query(
            "SELECT id FROM roles WHERE tenant_id = {$tenantId} AND name = 'admin'"
        )->fetchColumn();

        $this->assertGreaterThan(0, $roleId);

        $totalPermissions = (int) $this->db->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
        $granted = (int) $this->db->query(
            "SELECT COUNT(*) FROM role_permissions WHERE tenant_id = {$tenantId} AND role_id = {$roleId}"
        )->fetchColumn();

        $this->assertSame($totalPermissions, $granted, 'the admin role starts with the full catalogue');

        $user = $this->db->query(
            "SELECT id, is_admin, is_active, username FROM users
              WHERE tenant_id = {$tenantId} AND email = 'admin@acme.test'"
        )->fetch();

        $this->assertNotFalse($user);
        $this->assertSame(1, (int) $user['is_admin']);
        $this->assertSame(1, (int) $user['is_active']);
        $this->assertNotSame('', (string) $user['username']);

        $linked = (int) $this->db->query(
            "SELECT COUNT(*) FROM user_roles
              WHERE tenant_id = {$tenantId} AND user_id = " . (int) $user['id'] . " AND role_id = {$roleId}"
        )->fetchColumn();

        $this->assertSame(1, $linked);

        $this->assertSame(8, (int) $this->db->query(
            "SELECT COUNT(*) FROM gatepass_statuses WHERE tenant_id = {$tenantId}"
        )->fetchColumn());

        $this->assertSame(5, (int) $this->db->query(
            "SELECT COUNT(*) FROM visit_statuses WHERE tenant_id = {$tenantId}"
        )->fetchColumn());

        $this->assertSame(1, (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_audit_logs
              WHERE action = 'tenant.created' AND subject_type = 'tenant' AND subject_id = {$tenantId}"
        )->fetchColumn());
    }

    public function testProvisioningRejectsDuplicateCodesAndWeakPasswords(): void
    {
        $service = new TenantProvisioningService($this->db);
        $service->create($this->tenantInput());

        try {
            $service->create($this->tenantInput(['admin_email' => 'second@acme.test']));
            $this->fail('a duplicate tenant code must be rejected');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }

        try {
            $service->create($this->tenantInput([
                'code' => 'second-acme',
                'admin_password' => 'short',
            ]));
            $this->fail('a weak administrator password must be rejected');
        } catch (RuntimeException $e) {
            // The exact floor comes from config('security.password.min_length').
            $this->assertStringContainsString('Password must be at least', $e->getMessage());
            $this->assertStringContainsString('characters', $e->getMessage());
        }
    }

    public function testImpersonationKeepsPlatformIdentityAndIsFullyAudited(): void
    {
        $tenantId = (new TenantProvisioningService($this->db))->create($this->tenantInput());
        $platformId = $this->platformUser();
        $userId = $this->tenantUserId($tenantId);

        $_SESSION['platform_user'] = [
            'id'    => $platformId,
            'name'  => 'Platform Ops',
            'email' => 'ops@platform.test',
        ];

        $service = new PlatformImpersonationService($this->db);
        $result = $service->start($platformId, $tenantId, $userId, 'Ticket 42');

        $this->assertTrue($result['ok'], $result['error'] ?? '');
        $this->assertSame($tenantId, (int) $_SESSION['user']['tenant_id']);
        $this->assertSame($userId, (int) $_SESSION['user']['id']);
        $this->assertSame($platformId, (int) $_SESSION['platform_user']['id'], 'platform identity survives');

        $session = $_SESSION[PlatformImpersonationService::SESSION_KEY];
        $this->assertNotEmpty($session['token']);

        $row = $this->db->query(
            'SELECT token_hash, ended_at, expires_at FROM platform_impersonations WHERE id = '
            . (int) $session['id']
        )->fetch();

        $this->assertNotSame($session['token'], $row['token_hash'], 'only the hash is stored');
        $this->assertSame(hash('sha256', $session['token']), $row['token_hash']);
        $this->assertNull($row['ended_at']);
        $this->assertGreaterThan(time(), strtotime((string) $row['expires_at']));

        $this->assertSame(1, (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'tenant.impersonation_started'"
        )->fetchColumn());

        $returnedTenant = $service->stop('test_return');

        $this->assertSame($tenantId, $returnedTenant);
        $this->assertArrayNotHasKey('user', $_SESSION);
        $this->assertArrayNotHasKey('auth_at', $_SESSION);
        $this->assertArrayNotHasKey(PlatformImpersonationService::SESSION_KEY, $_SESSION);
        $this->assertSame($platformId, (int) $_SESSION['platform_user']['id']);

        $row = $this->db->query(
            'SELECT ended_at, end_reason FROM platform_impersonations WHERE id = ' . (int) $session['id']
        )->fetch();

        $this->assertNotNull($row['ended_at']);
        $this->assertSame('test_return', $row['end_reason']);

        $this->assertSame(1, (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'tenant.impersonation_ended'"
        )->fetchColumn());
    }

    public function testExpiredGrantIsRejectedAndClearsTheTenantIdentity(): void
    {
        $tenantId = (new TenantProvisioningService($this->db))->create($this->tenantInput());
        $platformId = $this->platformUser();
        $userId = $this->tenantUserId($tenantId);

        $_SESSION['platform_user'] = ['id' => $platformId, 'name' => 'Ops', 'email' => 'ops@platform.test'];

        $service = new PlatformImpersonationService($this->db);
        $result = $service->start($platformId, $tenantId, $userId);

        $this->assertTrue($result['ok'], $result['error'] ?? '');

        $impersonationId = (int) $_SESSION[PlatformImpersonationService::SESSION_KEY]['id'];

        $this->db->exec(
            'UPDATE platform_impersonations
                SET expires_at = ' . $this->db->quote(date('Y-m-d H:i:s', time() - 60)) . '
              WHERE id = ' . $impersonationId
        );

        $check = $service->validate();

        $this->assertFalse($check['ok']);
        $this->assertSame('expired', $check['reason']);
        $this->assertArrayNotHasKey('user', $_SESSION);
        $this->assertArrayNotHasKey(PlatformImpersonationService::SESSION_KEY, $_SESSION);
        $this->assertSame($platformId, (int) $_SESSION['platform_user']['id']);

        $row = $this->db->query(
            'SELECT end_reason FROM platform_impersonations WHERE id = ' . $impersonationId
        )->fetch();

        $this->assertSame('expired', $row['end_reason']);

        $this->assertSame(1, (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_audit_logs WHERE action = 'tenant.impersonation_expired'"
        )->fetchColumn());
    }

    public function testNonSuperadminPlatformUserCannotImpersonate(): void
    {
        $tenantId = (new TenantProvisioningService($this->db))->create($this->tenantInput());
        $platformId = $this->platformUser(false);
        $userId = $this->tenantUserId($tenantId);

        $_SESSION['platform_user'] = ['id' => $platformId, 'name' => 'Readonly', 'email' => 'readonly@platform.test'];

        $result = (new PlatformImpersonationService($this->db))
            ->start($platformId, $tenantId, $userId);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('superadmin', $result['error'] ?? '');
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function tenantInput(array $overrides = []): array
    {
        return array_merge([
            'name'           => 'Acme Support Ltd',
            'code'           => 'acme-support',
            'email'          => 'hello@acme.test',
            'phone'          => '+254700000001',
            'country'        => 'Kenya',
            'admin_first_name' => 'Acme',
            'admin_last_name'  => 'Admin',
            'admin_email'      => 'admin@acme.test',
            'admin_password'   => 'Str0ng!Passw0rd9',
        ], $overrides);
    }

    private function platformUser(bool $superadmin = true): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO platform_users (name, email, password_hash, is_superadmin)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $superadmin ? 'Platform Ops' : 'Readonly Ops',
            ($superadmin ? 'ops' : 'readonly') . bin2hex(random_bytes(4)) . '@platform.test',
            password_hash('Platform!2026x', PASSWORD_BCRYPT, ['cost' => 4]),
            $superadmin ? 1 : 0,
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
