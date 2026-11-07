<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Permission;
use App\Core\PlatformPolicy;
use App\Modules\Platform\Services\ModuleEntitlementService;
use App\Modules\Platform\Services\PlatformAuthService;
use App\Modules\Platform\Services\PlatformSettingsService;

final class PlatformPolicyTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PlatformPolicy::flush();
        Permission::flush();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        PlatformPolicy::flush();
        Permission::flush();

        parent::tearDown();
    }

    public function testFeaturesDefaultOnAndCanBeDisabledGlobally(): void
    {
        $this->assertTrue(PlatformPolicy::feature('public_pre_registration'));

        // Form semantics: the submitted set is the complete enabled set.
        (new PlatformSettingsService($this->db))->saveFeatures([
            'tenant_company_settings' => '1',
            'tenant_email_settings'   => '1',
            'tenant_gate_management'  => '1',
            'gate_device_scanning'    => '1',
        ]);

        $this->assertFalse(PlatformPolicy::feature('public_pre_registration'));
        $this->assertTrue(PlatformPolicy::feature('tenant_company_settings'));
    }

    public function testRestrictedPermissionIsDeniedEvenWhenGranted(): void
    {
        $permissionId = (int) $this->db->query(
            "SELECT p.id
               FROM permissions p
               INNER JOIN modules m ON m.id = p.module_id
               INNER JOIN actions a ON a.id = p.action_id
              WHERE m.name = 'visitors' AND a.name = 'create'
              LIMIT 1"
        )->fetchColumn();

        $this->assertGreaterThan(0, $permissionId);

        $this->db->exec("INSERT INTO roles (tenant_id, name) VALUES (1, 'Ops')");
        $roleId = (int) $this->db->lastInsertId();

        $this->db->exec("INSERT INTO role_permissions (tenant_id, role_id, permission_id)
                         VALUES (1, {$roleId}, {$permissionId})");
        $this->db->exec("INSERT INTO user_roles (tenant_id, user_id, role_id)
                         VALUES (1, 1, {$roleId})");

        $_SESSION['user'] = ['id' => 1, 'tenant_id' => 1];
        Permission::flush();

        $this->assertTrue((new Permission($this->db))->can('visitors.create'));

        (new PlatformSettingsService($this->db))->saveRestrictedPermissions(['visitors.create']);
        Permission::flush();

        $this->assertFalse(
            (new Permission($this->db))->can('visitors.create'),
            'a platform-restricted permission is withheld at runtime'
        );

        (new PlatformSettingsService($this->db))->saveRestrictedPermissions([]);
        Permission::flush();

        $this->assertTrue((new Permission($this->db))->can('visitors.create'));
    }

    public function testCoreModulesStayOnAndDependenciesAreEnforced(): void
    {
        $service = new ModuleEntitlementService($this->db);
        $names   = array_column($service->catalogue(), 'name');

        $withoutVisits = array_values(array_diff($names, ['visits']));
        $this->assertNotEmpty(
            $service->save(1, $withoutVisits),
            'badges must not be left enabled without visits'
        );
        $this->assertTrue($service->isEnabled(1, 'settings'));

        $withoutVisitors = array_values(array_diff($names, ['visitors']));
        $this->assertSame(
            ['visits requires visitors to be enabled.'],
            $service->save(1, $withoutVisitors)
        );

        $this->assertSame([], $service->save(1, array_values(array_diff($names, ['badges']))));

        $this->assertFalse($service->isEnabled(1, 'badges'));
        $this->assertTrue($service->isEnabled(1, 'gatepass'));
        $this->assertTrue($service->isEnabled(1, 'settings'), 'core modules cannot be switched off');
    }

    public function testPlatformLoginDoesNotCreateATenantSession(): void
    {
        $hash = password_hash('Platform!2026x', PASSWORD_BCRYPT, ['cost' => 4]);

        $stmt = $this->db->prepare(
            'INSERT INTO platform_users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        $stmt->execute(['Platform Ops', 'ops@platform.test', $hash]);

        $this->assertTrue(
            (new PlatformAuthService())->attempt('ops@platform.test', 'Platform!2026x')
        );

        $this->assertSame('ops@platform.test', $_SESSION['platform_user']['email']);
        $this->assertArrayNotHasKey('user', $_SESSION, 'platform sign-in must not mint a tenant identity');
    }
}
