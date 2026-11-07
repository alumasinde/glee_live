<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Permission;
use App\Modules\Gatepass\Policies\GatepassPolicy;
use PDO;

/**
 * Guards the permission granularity added in 0026: badges split into
 * view/issue/return, gatepass types out of settings, and the gatepass detail
 * screen applying the same department scope as the listing.
 */
final class PermissionHardeningTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Permission::flush();
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);
        Permission::flush();

        parent::tearDown();
    }

    /** @return list<string> module.action pairs from the permission catalogue */
    private function catalog(): array
    {
        $rows = $this->db->query(
            'SELECT m.name AS module, a.name AS action
             FROM permissions p
             JOIN modules m ON m.id = p.module_id
             JOIN actions a ON a.id = p.action_id'
        )->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): string => $row['module'] . '.' . $row['action'], $rows);
    }

    /**
     * Sign the seeded tenant-1 account in with exactly the given permissions.
     *
     * @param list<string> $permissions
     */
    private function grantOnly(array $permissions): int
    {
        $userId = $this->db->query('SELECT id FROM users WHERE tenant_id = 1 ORDER BY id LIMIT 1')->fetchColumn();
        self::assertNotFalse($userId, 'tenant 1 needs a seeded user');

        // Isolate the account so the effective set is exactly what we grant.
        $this->db->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([(int) $userId]);

        $this->db->prepare('INSERT INTO roles (tenant_id, name) VALUES (1, ?)')
            ->execute(['hardening-test-' . bin2hex(random_bytes(4))]);
        $roleId = (int) $this->db->lastInsertId();

        $this->db->prepare('INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, 1)')
            ->execute([(int) $userId, $roleId]);

        foreach ($permissions as $permission) {
            [$module, $action] = explode('.', $permission, 2);

            $this->db->prepare(
                'INSERT INTO role_permissions (tenant_id, role_id, permission_id)
                 SELECT 1, ?, p.id
                 FROM permissions p
                 JOIN modules m ON m.id = p.module_id AND m.name = ?
                 JOIN actions a ON a.id = p.action_id AND a.name = ?'
            )->execute([$roleId, $module, $action]);
        }

        $_SESSION['user'] = ['id' => (int) $userId, 'tenant_id' => 1];
        Permission::flush();

        return (int) $userId;
    }

    public function testBadgeAndGatepassTypeKeysReplacedTheUmbrellaPermissions(): void
    {
        $catalog = $this->catalog();

        foreach ([
            'badges.view',
            'badges.issue',
            'badges.return',
            'gatepass_types.view',
            'gatepass_types.update',
        ] as $key) {
            self::assertContains($key, $catalog);
        }

        self::assertNotContains('visitors.issue_badge', $catalog);
    }

    public function testIssuingABadgeDoesNotGrantReturningIt(): void
    {
        $this->grantOnly(['badges.issue']);

        $permission = new Permission($this->db);

        self::assertTrue($permission->can('badges.issue'));
        self::assertFalse($permission->can('badges.return'));
        self::assertFalse($permission->can('badges.view'));
    }

    public function testGatepassDetailIsDepartmentScopedWithoutViewAll(): void
    {
        $policy = new GatepassPolicy(new Permission($this->db));
        $user   = ['id' => 10, 'department_id' => 5];

        self::assertTrue($policy->view($user, ['created_by' => 99, 'department_id' => 5]));
        self::assertFalse($policy->view($user, ['created_by' => 99, 'department_id' => 9]));
        self::assertTrue($policy->view($user, ['created_by' => 10, 'department_id' => 9]));
        self::assertTrue($policy->view($user, ['created_by' => 99, 'department_id' => null]));
    }

    public function testViewAllUnlocksCrossDepartmentGatepass(): void
    {
        $userId = $this->grantOnly(['gatepass.view', 'gatepass.view_all']);

        $policy = new GatepassPolicy(new Permission($this->db));

        self::assertTrue($policy->view(
            ['id' => $userId, 'department_id' => 5],
            ['created_by' => 99, 'department_id' => 9]
        ));
    }
}
