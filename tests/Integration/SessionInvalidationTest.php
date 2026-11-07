<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Modules\Auth\Repositories\UserRepository;

/**
 * users.tokens_invalid_before was written on every login, reset and password
 * change but never read, so a stolen session survived all of them. These tests
 * cover the read side: App\Core\Auth::sessionIsValid().
 */
final class SessionInvalidationTest extends DatabaseTestCase
{
    private const USER_ID = 990042;
    private const EMAIL   = 'stale.session@test.local';

    protected function setUp(): void
    {
        parent::setUp();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];

        $this->db->exec(
            'INSERT INTO users (id, tenant_id, email, password_hash, first_name, last_name, username, is_admin, is_active)
             VALUES (' . self::USER_ID . ", 1, '" . self::EMAIL . "', 'x', 'Stale', 'Session', 'stalesession', 0, 1)"
        );
    }

    protected function tearDown(): void
    {
        Auth::forget();
        $_SESSION = [];

        parent::tearDown();
    }

    private function startSessionAs(string $authAt, int $userId = self::USER_ID, int $tenantId = 1): void
    {
        // forget() also clears the per-request memo, so each case is evaluated fresh.
        Auth::forget();

        $_SESSION['user'] = ['id' => $userId, 'tenant_id' => $tenantId];
        $_SESSION['auth_at'] = $authAt;
    }

    private function stamp(string $value): void
    {
        $stmt = $this->db->prepare('UPDATE users SET tokens_invalid_before = ? WHERE id = ?');
        $stmt->execute([$value, self::USER_ID]);
    }

    public function testSessionIssuedBeforePasswordChangeIsRejected(): void
    {
        $this->stamp('2026-01-01 10:00:00');
        $this->startSessionAs('2026-01-01 09:00:00');

        $this->assertFalse(Auth::sessionIsValid());
    }

    public function testSessionIssuedAfterPasswordChangeIsAccepted(): void
    {
        $this->stamp('2026-01-01 10:00:00');
        $this->startSessionAs('2026-01-01 10:00:01');

        $this->assertTrue(Auth::sessionIsValid());
    }

    public function testSessionIssuedInTheSameSecondAsTheChangeSurvives(): void
    {
        // The stamp is what recordLoginSuccess() returns and the session stores
        // verbatim, so equality must count as valid (<=, not <).
        $this->stamp('2026-01-01 10:00:00');
        $this->startSessionAs('2026-01-01 10:00:00');

        $this->assertTrue(Auth::sessionIsValid());
    }

    public function testDisabledAccountSessionIsRejected(): void
    {
        $this->stamp('2026-01-01 10:00:00');
        $this->startSessionAs('2026-01-02 10:00:00');

        $this->db->exec('UPDATE users SET is_active = 0 WHERE id = ' . self::USER_ID);

        $this->assertFalse(Auth::sessionIsValid());
    }

    public function testInvalidateSessionsEndsAnExistingSession(): void
    {
        $this->startSessionAs('2026-01-01 09:00:00');

        $old = $_SESSION['auth_at'];
        $stamp = (new UserRepository())->invalidateSessions(1, self::USER_ID);

        $this->startSessionAs($old);
        $this->assertFalse(Auth::sessionIsValid(), 'the pre-change session must stop working');

        $this->startSessionAs($stamp);
        $this->assertTrue(Auth::sessionIsValid(), 'a re-issued session must still work');
    }

    public function testRecordLoginSuccessReturnsAStampTheSessionCanUse(): void
    {
        $stamp = (new UserRepository())->recordLoginSuccess(1, self::USER_ID);

        $this->startSessionAs($stamp);

        $this->assertTrue(Auth::sessionIsValid());
    }

    public function testUserWithoutAnyRoleCanStillBeFoundForLogin(): void
    {
        // Issue: the login lookup INNER JOINed user_roles, so an account with no
        // role could never authenticate (and could not reset its password).
        $this->db->exec('DELETE FROM user_roles WHERE user_id = ' . self::USER_ID);

        $user = (new UserRepository())->findForLogin(1, self::EMAIL);

        $this->assertNotNull($user);
        $this->assertNull($user['role']);
    }

    public function testMultipleRolesAreGatheredIntoOneValue(): void
    {
        $this->db->exec("INSERT IGNORE INTO roles (id, tenant_id, name) VALUES
            (990001, 1, 'Gate Guard'),
            (990002, 1, 'Approver')");

        $this->db->exec('INSERT IGNORE INTO user_roles (user_id, role_id, tenant_id) VALUES
            (' . self::USER_ID . ', 990001, 1),
            (' . self::USER_ID . ', 990002, 1)');

        $user = (new UserRepository())->findForLogin(1, self::EMAIL);

        $this->assertNotNull($user);
        $this->assertSame('Approver, Gate Guard', $user['role']);
    }
}
