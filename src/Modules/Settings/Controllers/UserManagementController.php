<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use PDO;

class UserManagementController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        // FIX: Use Auth helper instead of raw $_SESSION check
        if (! Auth::check()) {
            Response::redirect('/login');
        }

        $this->db = DB::connect();
    }

    /**
     * List users
     */
    public function index()
    {
        $tenantId = Tenant::require();

        $stmt = $this->db->prepare("
            SELECT u.*,
                   GROUP_CONCAT(DISTINCT r.name) AS roles
            FROM users u
            LEFT JOIN user_roles ur
                ON u.id        = ur.user_id
               AND ur.tenant_id = u.tenant_id
            LEFT JOIN roles r
                ON ur.role_id   = r.id
               AND r.tenant_id  = u.tenant_id
            WHERE u.tenant_id = :tenant_id
            GROUP BY u.id
            ORDER BY u.id DESC
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $this->view('Settings::users.index', [
            'title' => 'User Management',
            'users' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        $tenantId = Tenant::require();

        $stmt = $this->db->prepare("
            SELECT id, name
            FROM roles
            WHERE tenant_id = :tenant_id
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $this->view('Settings::users.create', [
            'title' => 'Create User',
            'roles' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    /**
     * Store user
     */
    public function store(Request $request)
    {
        $tenantId = Tenant::require();

        $email     = strtolower(trim((string) $request->input('email')));
        $username  = strtolower(trim((string) $request->input('username')));
        $firstName = trim((string) $request->input('first_name'));
        $lastName  = trim((string) $request->input('last_name'));
        $password  = (string) $request->input('password');
        $roles     = $request->input('roles', []);

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email.');
        }

        if ($username === '') {
            throw new \RuntimeException('Username is required.');
        }

        if ($password === '') {
            throw new \RuntimeException('Password is required.');
        }

        // Uniqueness check — scoped to tenant (schema: uk_user_tenant_email, uk_user_tenant_username)
        $check = $this->db->prepare("
            SELECT 1
            FROM users
            WHERE tenant_id = :tenant_id
              AND (email = :email OR username = :username)
            LIMIT 1
        ");

        $check->execute([
            ':tenant_id' => $tenantId,
            ':email'     => $email,
            ':username'  => $username,
        ]);

        if ($check->fetch()) {
            throw new \RuntimeException('Email or username already exists.');
        }

        // FIX: Use DB::transaction() instead of manual beginTransaction/commit/rollback
        DB::transaction(function () use ($tenantId, $email, $username, $firstName, $lastName, $password, $roles) {

            $stmt = $this->db->prepare("
                INSERT INTO users
                    (tenant_id, email, password_hash, first_name, last_name, username)
                VALUES
                    (:tenant_id, :email, :password, :first_name, :last_name, :username)
            ");

            $stmt->execute([
                ':tenant_id'  => $tenantId,
                ':email'      => $email,
                ':password'   => password_hash($password, PASSWORD_DEFAULT),
                ':first_name' => $firstName,
                ':last_name'  => $lastName,
                ':username'   => $username,
            ]);

            $userId = (int) $this->db->lastInsertId();

            if (! empty($roles)) {
                $roleStmt = $this->db->prepare("
                    INSERT INTO user_roles (user_id, role_id, tenant_id)
                    VALUES (:user_id, :role_id, :tenant_id)
                ");

                foreach ($roles as $roleId) {
                    $roleStmt->execute([
                        ':user_id'   => $userId,
                        ':role_id'   => (int) $roleId,
                        ':tenant_id' => $tenantId,
                    ]);
                }
            }
        });

        return $this->redirect('/settings/users');
    }

    /**
     * Edit user
     */
    public function edit(Request $request, int $id)
    {
        $tenantId = Tenant::require();

        $stmt = $this->db->prepare("
            SELECT *
            FROM users
            WHERE id = :id
              AND tenant_id = :tenant_id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $editUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $editUser) {
            return $this->redirect('/settings/users');
        }

        $rolesStmt = $this->db->prepare("
            SELECT id, name
            FROM roles
            WHERE tenant_id = :tenant_id
        ");
        $rolesStmt->execute([':tenant_id' => $tenantId]);

        $userRolesStmt = $this->db->prepare("
            SELECT role_id
            FROM user_roles
            WHERE user_id  = :user_id
              AND tenant_id = :tenant_id
        ");
        $userRolesStmt->execute([':user_id' => $id, ':tenant_id' => $tenantId]);

        return $this->view('Settings::users.edit', [
            'title'     => 'Edit User',
            'userData'  => $editUser,
            'roles'     => $rolesStmt->fetchAll(PDO::FETCH_ASSOC),
            'userRoles' => array_column($userRolesStmt->fetchAll(PDO::FETCH_ASSOC), 'role_id'),
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request, int $id)
    {
        $tenantId = Tenant::require();

        $email     = strtolower(trim((string) $request->input('email')));
        $username  = strtolower(trim((string) $request->input('username')));
        $firstName = trim((string) $request->input('first_name'));
        $lastName  = trim((string) $request->input('last_name'));
        $roleIds   = $request->input('roles', []);

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Invalid email.');
        }

        if ($username === '') {
            throw new \RuntimeException('Username is required.');
        }

        $check = $this->db->prepare("
            SELECT 1
            FROM users
            WHERE tenant_id = :tenant_id
              AND (email = :email OR username = :username)
              AND id != :id
            LIMIT 1
        ");

        $check->execute([
            ':tenant_id' => $tenantId,
            ':email'     => $email,
            ':username'  => $username,
            ':id'        => $id,
        ]);

        if ($check->fetch()) {
            throw new \RuntimeException('Email or username already exists.');
        }

        // FIX: Use DB::transaction()
        DB::transaction(function () use ($tenantId, $id, $email, $username, $firstName, $lastName, $roleIds) {

            $stmt = $this->db->prepare("
                UPDATE users
                SET first_name = :first_name,
                    last_name  = :last_name,
                    email      = :email,
                    username   = :username
                WHERE id        = :id
                  AND tenant_id = :tenant_id
            ");

            $stmt->execute([
                ':first_name' => $firstName,
                ':last_name'  => $lastName,
                ':email'      => $email,
                ':username'   => $username,
                ':id'         => $id,
                ':tenant_id'  => $tenantId,
            ]);

            // Reset roles
            $this->db->prepare("
                DELETE FROM user_roles
                WHERE user_id   = :user_id
                  AND tenant_id = :tenant_id
            ")->execute([':user_id' => $id, ':tenant_id' => $tenantId]);

            if (! empty($roleIds)) {
                $stmt = $this->db->prepare("
                    INSERT INTO user_roles (user_id, role_id, tenant_id)
                    VALUES (:user_id, :role_id, :tenant_id)
                ");

                foreach ($roleIds as $roleId) {
                    $stmt->execute([
                        ':user_id'   => $id,
                        ':role_id'   => (int) $roleId,
                        ':tenant_id' => $tenantId,
                    ]);
                }
            }
        });

        return $this->redirect("/settings/users/{$id}/edit");
    }

    /**
     * Profile page
     */
    public function profile()
    {
        return $this->view('Settings::users.profile', [
            'title' => 'My Profile',
            'user'  => Auth::user(),
        ]);
    }

    /**
     * Update profile
     */
    public function updateProfile(Request $request)
    {
        $user     = Auth::user();
        $tenantId = Tenant::require();
        $userId   = Auth::id();

        $firstName = trim((string) $request->input('first_name'));
        $lastName  = trim((string) $request->input('last_name'));
        $email     = strtolower(trim((string) $request->input('email')));

        $currentPassword = (string) $request->input('current_password');
        $newPassword     = (string) $request->input('new_password');
        $confirmPassword = (string) $request->input('confirm_password');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->view('Settings::users.profile', [
                'title' => 'My Profile',
                'user'  => $user,
                'error' => 'Invalid email address.',
            ]);
        }

        try {
            DB::transaction(function () use ($tenantId, $userId, $firstName, $lastName, $email, $currentPassword, $newPassword, $confirmPassword) {

                $stmt = $this->db->prepare("
                    UPDATE users
                    SET first_name = :first_name,
                        last_name  = :last_name,
                        email      = :email
                    WHERE id        = :id
                      AND tenant_id = :tenant_id
                ");

                $stmt->execute([
                    ':first_name' => $firstName,
                    ':last_name'  => $lastName,
                    ':email'      => $email,
                    ':id'         => $userId,
                    ':tenant_id'  => $tenantId,
                ]);

                if ($newPassword !== '') {

                    if ($newPassword !== $confirmPassword) {
                        throw new \RuntimeException('Passwords do not match.');
                    }

                    $stmt = $this->db->prepare("
                        SELECT password_hash
                        FROM users
                        WHERE id        = :id
                          AND tenant_id = :tenant_id
                        LIMIT 1
                    ");

                    $stmt->execute([':id' => $userId, ':tenant_id' => $tenantId]);
                    $dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (! $dbUser || ! password_verify($currentPassword, $dbUser['password_hash'])) {
                        throw new \RuntimeException('Current password is incorrect.');
                    }

                    $this->db->prepare("
                        UPDATE users
                        SET password_hash = :password
                        WHERE id        = :id
                          AND tenant_id = :tenant_id
                    ")->execute([
                        ':password'  => password_hash($newPassword, PASSWORD_DEFAULT),
                        ':id'        => $userId,
                        ':tenant_id' => $tenantId,
                    ]);
                }
            });

            // Sync session
            $_SESSION['user']['first_name'] = $firstName;
            $_SESSION['user']['last_name']  = $lastName;
            $_SESSION['user']['email']      = $email;

            return $this->redirect('/settings/users/profile');

        } catch (\Throwable $e) {

            return $this->view('Settings::users.profile', [
                'title' => 'My Profile',
                'user'  => $user,
                'error' => $e->getMessage(),
            ]);
        }
    }
}