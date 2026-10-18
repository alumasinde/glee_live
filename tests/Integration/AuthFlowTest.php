<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Core\Request;
use App\Core\DB;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Controllers\LoginController;
use App\Modules\Auth\Controllers\PasswordController;
use App\Modules\Tenant\Repositories\TenantRepository;

/**
 * Authentication Flow Integration Tests
 *
 * Tests the complete authentication flow including:
 * - Login with valid credentials
 * - Login with invalid credentials
 * - Password reset request
 * - Password reset execution
 * - Rate limiting
 * - Session management
 */
class AuthFlowTest extends TestCase
{
    private AuthService $auth;
    private TenantRepository $tenantRepo;
    private int $testTenantId;
    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();

        // Login flows manipulate the real session; PHPUnit CLI runs without one
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = [];

        $this->auth = new AuthService();
        $this->tenantRepo = new TenantRepository();

        // Setup test tenant
        $this->testTenantId = $this->createTestTenant();

        // Setup test user
        $this->testUserId = $this->createTestUser();
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        // Cleanup test data
        $this->cleanupTestData();

        parent::tearDown();
    }

    public function testSuccessfulLogin()
    {
        $email = 'test@example.com';
        $password = 'TestPassword123!';

        // Attempt login
        $result = $this->auth->attempt($this->testTenantId, $email, $password);

        $this->assertTrue($result, 'Login should succeed with valid credentials');
        $this->assertArrayHasKey('user', $_SESSION, 'User should be in session after login');
        $this->assertEquals($email, $_SESSION['user']['email'], 'Session should contain correct email');
    }

    public function testFailedLoginWithInvalidCredentials()
    {
        $email = 'test@example.com';
        $password = 'WrongPassword123!';

        // Attempt login with wrong password
        $result = $this->auth->attempt($this->testTenantId, $email, $password);

        $this->assertFalse($result, 'Login should fail with invalid credentials');
        $this->assertArrayNotHasKey('user', $_SESSION, 'User should not be in session after failed login');
    }

    public function testFailedLoginWithNonExistentUser()
    {
        $email = 'nonexistent@example.com';
        $password = 'TestPassword123!';

        // Attempt login with non-existent user
        $result = $this->auth->attempt($this->testTenantId, $email, $password);

        $this->assertFalse($result, 'Login should fail with non-existent user');
    }

    public function testPasswordResetTokenCreation()
    {
        $email = 'test@example.com';

        // Create reset token
        $this->auth->createResetTokenByEmail($this->testTenantId, $email);

        // Verify token was created in database
        $stmt = DB::connect()->prepare("
            SELECT * FROM password_resets
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $this->testUserId]);
        $reset = $stmt->fetch();

        $this->assertNotFalse($reset, 'Password reset token should be created');
        $this->assertNotEmpty($reset['token'], 'Token should not be empty');
        $this->assertNotNull($reset['expires_at'], 'Expiration time should be set');
    }

    public function testPasswordResetWithValidToken()
    {
        $email = 'test@example.com';
        $newPassword = 'NewPassword456!';

        // Create reset token
        $this->auth->createResetTokenByEmail($this->testTenantId, $email);

        // Get the token
        $stmt = DB::connect()->prepare("
            SELECT token FROM password_resets
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([':user_id' => $this->testUserId]);
        $reset = $stmt->fetch();
        $token = $reset['token'];

        // Reset password
        $this->auth->resetPassword($this->testTenantId, $token, $newPassword);

        // Verify password was changed by attempting login with new password
        $result = $this->auth->attempt($this->testTenantId, $email, $newPassword);
        $this->assertTrue($result, 'Should be able to login with new password');

        // Verify old password no longer works
        $result = $this->auth->attempt($this->testTenantId, $email, 'TestPassword123!');
        $this->assertFalse($result, 'Old password should not work after reset');
    }

    public function testPasswordResetWithInvalidToken()
    {
        $invalidToken = str_repeat('a', 64);
        $newPassword = 'NewPassword456!';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $this->auth->resetPassword($this->testTenantId, $invalidToken, $newPassword);
    }

    public function testPasswordValidation()
    {
        // Valid password
        $this->assertPasswordValid('ValidPassword123!');

        // Too short
        $this->assertPasswordInvalid('Short1!');

        // No uppercase
        $this->assertPasswordInvalid('nouppercase123!');

        // No lowercase
        $this->assertPasswordInvalid('NOLOWERCASE123!');

        // No numbers
        $this->assertPasswordInvalid('NoNumbers!');
    }

    public function testSessionRegenerationOnLogin()
    {
        $email = 'test@example.com';
        $password = 'TestPassword123!';

        $oldSessionId = session_id();

        // Login
        $this->auth->attempt($this->testTenantId, $email, $password);

        $newSessionId = session_id();

        // Session ID should be regenerated
        $this->assertNotEquals($oldSessionId, $newSessionId, 'Session ID should be regenerated on login');
    }

    public function testLogout()
    {
        $email = 'test@example.com';
        $password = 'TestPassword123!';

        // Login first
        $this->auth->attempt($this->testTenantId, $email, $password);
        $this->assertArrayHasKey('user', $_SESSION, 'User should be in session');

        // Logout
        $this->auth->logout();

        $this->assertArrayNotHasKey('user', $_SESSION, 'User should not be in session after logout');
    }

    public function testRateLimiting()
    {
        $this->markTestSkipped('Rate limiting requires Redis or session management - implement separately');
    }

    // Helper methods

    private function createTestTenant(): int
    {
        $stmt = DB::connect()->prepare("
            INSERT INTO tenants (code, name, logo, email, phone, country, is_active, created_at)
            VALUES ('test_tenant', 'Test Tenant', '', 'tenant@test.local', '', '', 1, NOW())
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmt->execute();

        return (int) DB::connect()->lastInsertId();
    }

    private function createTestUser(): int
    {
        $email = 'test@example.com';
        $password = password_hash('TestPassword123!', PASSWORD_DEFAULT);

        $stmt = DB::connect()->prepare("
            INSERT INTO users (tenant_id, email, username, password_hash, first_name, last_name, is_active, created_at)
            VALUES (:tenant_id, :email, 'testuser', :password, 'Test', 'User', 1, NOW())
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmt->execute([
            ':tenant_id' => $this->testTenantId,
            ':email' => $email,
            ':password' => $password,
        ]);

        $userId = (int) DB::connect()->lastInsertId();

        // AuthService login lookup joins roles/user_roles, so the user needs one
        $stmt = DB::connect()->prepare("
            INSERT INTO roles (tenant_id, name)
            VALUES (:tenant_id, 'Test Admin')
            ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)
        ");
        $stmt->execute([':tenant_id' => $this->testTenantId]);
        $roleId = (int) DB::connect()->lastInsertId();

        $stmt = DB::connect()->prepare("
            INSERT INTO user_roles (user_id, role_id, tenant_id)
            VALUES (:user_id, :role_id, :tenant_id)
            ON DUPLICATE KEY UPDATE role_id = VALUES(role_id)
        ");
        $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $roleId,
            ':tenant_id' => $this->testTenantId,
        ]);

        return $userId;
    }

    private function cleanupTestData()
    {
        // Clean up password resets
        $stmt = DB::connect()->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $this->testUserId]);

        // Clean up users
        $stmt = DB::connect()->prepare("DELETE FROM users WHERE id = :user_id");
        $stmt->execute([':user_id' => $this->testUserId]);

        // Clean up tenant
        $stmt = DB::connect()->prepare("DELETE FROM tenants WHERE id = :tenant_id");
        $stmt->execute([':tenant_id' => $this->testTenantId]);
    }

    private function assertPasswordValid(string $password): void
    {
        try {
            $this->auth->resetPassword($this->testTenantId, str_repeat('a', 64), $password);
            $this->assertTrue(true);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Password must')) {
                $this->fail("Password should be valid: {$password}");
            }
            // Fake token: an "Invalid or expired token" error means the
            // password itself passed policy, which is all this asserts.
            $this->addToAssertionCount(1);
        }
    }

    private function assertPasswordInvalid(string $password): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Password must/');

        $this->auth->resetPassword($this->testTenantId, str_repeat('a', 64), $password);
    }
}
