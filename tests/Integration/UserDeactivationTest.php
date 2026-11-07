<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Settings\Services\UserStatusService;
use RuntimeException;

/**
 * users.disable: deactivation replaces deletion, so it must actually stop the
 * account working, must not let an actor lock themselves (or the tenant) out,
 * and must stay inside the tenant.
 */
final class UserDeactivationTest extends DatabaseTestCase
{
    private const ACTOR  = 991001;
    private const TARGET = 991002;
    private const ADMIN  = 991003;
    private const OTHER  = 991004;

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION = ['user' => ['id' => self::ACTOR, 'tenant_id' => 1]];

        $this->db->exec("INSERT INTO users (id, tenant_id, email, password_hash, first_name, last_name, username, is_admin, is_active)
            VALUES
                (" . self::ACTOR . ", 1, 'deact.actor@test.local', 'x', 'Ada', 'Actor', 'deactactor', 0, 1),
                (" . self::TARGET . ", 1, 'deact.target@test.local', 'x', 'Tom', 'Target', 'deacttarget', 0, 1),
                (" . self::ADMIN . ", 1, 'deact.admin@test.local', 'x', 'Ann', 'Admin', 'deactadmin', 1, 1),
                (" . self::OTHER . ", 2, 'deact.other@test.local', 'x', 'Oli', 'Other', 'deactother', 0, 1)");

        // Make the fixture administrator the only active one, so the
        // last-administrator guard is exercised deterministically.
        $this->db->exec('UPDATE users SET is_active = 0
            WHERE tenant_id = 1 AND is_admin = 1 AND id <> ' . self::ADMIN);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];

        parent::tearDown();
    }

    private function service(): UserStatusService
    {
        return new UserStatusService($this->db);
    }

    private function isActive(int $userId): bool
    {
        $stmt = $this->db->prepare('SELECT is_active FROM users WHERE id = ?');
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn() === 1;
    }

    public function testDeactivationDisablesTheAccountAndEndsSessions(): void
    {
        $this->service()->setActive(1, self::TARGET, self::ACTOR, false);

        $this->assertFalse($this->isActive(self::TARGET));

        $stamp = $this->db->query(
            'SELECT tokens_invalid_before FROM users WHERE id = ' . self::TARGET
        )->fetchColumn();

        $this->assertNotEmpty($stamp, 'existing sessions must be revoked');

        $audit = $this->db->query(
            "SELECT COUNT(*) FROM audit_logs
              WHERE action = 'user.deactivated' AND entity_id = " . self::TARGET
        )->fetchColumn();

        $this->assertSame(1, (int) $audit);
    }

    public function testDeactivatedUserCanNoLongerLogIn(): void
    {
        $repo = new UserRepository();

        $this->assertNotNull($repo->findForLogin(1, 'deact.target@test.local'));

        $this->service()->setActive(1, self::TARGET, self::ACTOR, false);

        $this->assertNull($repo->findForLogin(1, 'deact.target@test.local'));
    }

    public function testActivationRestoresLogin(): void
    {
        $repo = new UserRepository();

        $this->service()->setActive(1, self::TARGET, self::ACTOR, false);
        $this->service()->setActive(1, self::TARGET, self::ACTOR, true);

        $this->assertTrue($this->isActive(self::TARGET));
        $this->assertNotNull($repo->findForLogin(1, 'deact.target@test.local'));

        $audit = $this->db->query(
            "SELECT COUNT(*) FROM audit_logs
              WHERE action = 'user.reactivated' AND entity_id = " . self::TARGET
        )->fetchColumn();

        $this->assertSame(1, (int) $audit);
    }

    public function testActorCannotDeactivateThemselves(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('your own account');

        try {
            $this->service()->setActive(1, self::ACTOR, self::ACTOR, false);
        } finally {
            $this->assertTrue($this->isActive(self::ACTOR), 'a refused change must not take effect');
        }
    }

    public function testLastActiveAdministratorCannotBeDeactivated(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('last active administrator');

        try {
            $this->service()->setActive(1, self::ADMIN, self::ACTOR, false);
        } finally {
            $this->assertTrue($this->isActive(self::ADMIN), 'the tenant must keep an administrator');
        }
    }

    public function testAdministratorCanBeDeactivatedWhenAnotherRemains(): void
    {
        $this->db->exec("INSERT INTO users (id, tenant_id, email, password_hash, first_name, last_name, username, is_admin, is_active)
            VALUES (991009, 1, 'deact.admin2@test.local', 'x', 'Bea', 'Backup', 'deactbackup', 1, 1)");

        $this->service()->setActive(1, self::ADMIN, self::ACTOR, false);

        $this->assertFalse($this->isActive(self::ADMIN));
    }

    public function testDeactivationIsTenantScoped(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('User not found');

        try {
            $this->service()->setActive(1, self::OTHER, self::ACTOR, false);
        } finally {
            $this->assertTrue($this->isActive(self::OTHER), 'tenant 2 rows are invisible to tenant 1');
        }
    }

    public function testPermissionIsSeededWithTheBlueprint(): void
    {
        $count = $this->db->query("
            SELECT COUNT(*)
              FROM permissions p
              JOIN modules m ON m.id = p.module_id AND m.name = 'users'
              JOIN actions a ON a.id = p.action_id AND a.name = 'disable'
        ")->fetchColumn();

        $this->assertSame(1, (int) $count);
    }
}
