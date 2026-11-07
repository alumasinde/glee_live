<?php

namespace App\Modules\Platform\Services;

use App\Core\DB;
use App\Modules\Auth\Services\AuthService;
use PDO;
use RuntimeException;

/**
 * Creates a tenant that can sign in immediately: the tenant row, an `admin`
 * role holding the current permission catalogue, and a first administrator.
 *
 * Lookup rows the core flows read unconditionally (statuses and visitor
 * types) are seeded from the canonical list so a new tenant is not born with
 * an empty gatepass pipeline. Workflows, gatepass types, gates, departments
 * and branding stay tenant-configured, exactly as they are for any tenant.
 */
class TenantProvisioningService
{
    private PDO $db;

    /** Subdomains that must never resolve to a tenant host. */
    private const RESERVED_CODES = [
        'www', 'platform', 'api', 'admin', 'app', 'mail', 'assets', 'static',
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    /**
     * @param array<string, mixed> $input
     * @throws RuntimeException on a validation or uniqueness failure
     */
    public function create(array $input, ?int $platformUserId = null): int
    {
        $data = $this->validate($input);
        $ownTransaction = !$this->db->inTransaction();

        if ($ownTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $tenantId = $this->persist($data, $platformUserId);

            if ($ownTransaction) {
                $this->db->commit();
            }

            return $tenantId;
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    private function validate(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        if (strlen($name) < 2 || strlen($name) > 255) {
            throw new RuntimeException('Tenant name must be between 2 and 255 characters.');
        }

        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $code = trim(preg_replace('/[^a-z0-9-]+/', '-', $code) ?? '', '-');

        if ($code === '' || strlen($code) > 120 || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $code)) {
            throw new RuntimeException('Tenant code must be a lowercase subdomain (letters, numbers and hyphens).');
        }

        if (in_array($code, self::RESERVED_CODES, true)) {
            throw new RuntimeException('That tenant code is reserved for the platform.');
        }

        $email = strtolower(trim((string) ($input['email'] ?? '')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid tenant contact email is required.');
        }

        $phone = trim((string) ($input['phone'] ?? ''));
        $country = trim((string) ($input['country'] ?? ''));

        if ($phone === '' || strlen($phone) > 50) {
            throw new RuntimeException('A tenant phone number is required (max 50 characters).');
        }

        if (strlen($country) > 120) {
            throw new RuntimeException('Country is too long (max 120 characters).');
        }

        $adminFirst = trim((string) ($input['admin_first_name'] ?? ''));
        $adminLast = trim((string) ($input['admin_last_name'] ?? ''));
        $adminEmail = strtolower(trim((string) ($input['admin_email'] ?? '')));
        $password = (string) ($input['admin_password'] ?? '');

        if ($adminFirst === '' || $adminLast === '' || strlen($adminFirst) > 120 || strlen($adminLast) > 120) {
            throw new RuntimeException('The first administrator needs a first and last name.');
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid administrator email is required.');
        }

        // Same policy as tenant user creation, including the configured floor.
        AuthService::assertStrongPassword($password);

        return [
            'name'         => $name,
            'code'         => $code,
            'email'        => $email,
            'phone'        => $phone,
            'country'      => $country,
            'admin_first'  => $adminFirst,
            'admin_last'   => $adminLast,
            'admin_email'  => $adminEmail,
            'admin_password' => $password,
        ];
    }

    /**
     * @param array<string, string> $data
     */
    private function persist(array $data, ?int $platformUserId): int
    {
        $check = $this->db->prepare('SELECT 1 FROM tenants WHERE code = ? LIMIT 1');
        $check->execute([$data['code']]);

        if ($check->fetchColumn()) {
            throw new RuntimeException('A tenant with that code already exists.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO tenants (name, code, logo, email, phone, country, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $data['name'],
            $data['code'],
            'acme-logo.png',
            $data['email'],
            $data['phone'],
            $data['country'] !== '' ? $data['country'] : null,
        ]);

        $tenantId = (int) $this->db->lastInsertId();

        // Administrator role: every current permission. Platform-restricted
        // keys are still denied at runtime by Permission, so granting them
        // here cannot bypass platform policy.
        $roleStmt = $this->db->prepare("INSERT INTO roles (tenant_id, name) VALUES (?, 'admin')");
        $roleStmt->execute([$tenantId]);
        $roleId = (int) $this->db->lastInsertId();

        $permissionIds = $this->db->query('SELECT id FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
        $grantStmt = $this->db->prepare(
            'INSERT IGNORE INTO role_permissions (tenant_id, role_id, permission_id) VALUES (?, ?, ?)'
        );

        foreach ($permissionIds as $permissionId) {
            $grantStmt->execute([$tenantId, $roleId, (int) $permissionId]);
        }

        $username = $this->uniqueUsername($tenantId, $data['admin_email']);

        $userStmt = $this->db->prepare(
            'INSERT INTO users
                (tenant_id, email, password_hash, first_name, last_name, username, is_active, is_admin)
             VALUES (?, ?, ?, ?, ?, ?, 1, 1)'
        );
        $userStmt->execute([
            $tenantId,
            $data['admin_email'],
            password_hash($data['admin_password'], PASSWORD_DEFAULT),
            $data['admin_first'],
            $data['admin_last'],
            $username,
        ]);

        $userId = (int) $this->db->lastInsertId();

        $linkStmt = $this->db->prepare(
            'INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)'
        );
        $linkStmt->execute([$userId, $roleId, $tenantId]);

        $this->seedLookups($tenantId);

        PlatformAuditService::log('tenant.created', 'tenant', $tenantId, [
            'code'          => $data['code'],
            'admin_email'   => $data['admin_email'],
            'admin_user_id' => $userId,
            'created_by'    => $platformUserId,
        ]);

        return $tenantId;
    }

    private function uniqueUsername(int $tenantId, string $email): string
    {
        $base = strtolower(explode('@', $email)[0]);
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?? '';
        $base = trim($base, '._-');
        $base = substr($base !== '' ? $base : 'admin', 0, 100);

        $candidate = $base;
        $suffix = 1;

        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE tenant_id = ? AND username = ? LIMIT 1');

        do {
            $stmt->execute([$tenantId, $candidate]);

            if (!$stmt->fetchColumn()) {
                return $candidate;
            }

            $suffix++;
            $candidate = substr($base, 0, 100) . $suffix;
        } while ($suffix < 1000);

        throw new RuntimeException('Could not allocate a unique username for the administrator.');
    }

    /**
     * Seed reference rows the core flows read without a settings screen.
     */
    private function seedLookups(int $tenantId): void
    {
        $gatepassStatuses = [
            ['Pending', 'pending'],
            ['Approved', 'approved'],
            ['Rejected', 'rejected'],
            ['Checked In', 'checked_in'],
            ['Checked Out', 'checked_out'],
            ['Cancelled', 'cancelled'],
            ['Expired', 'expired'],
            ['Returned', 'returned'],
        ];

        $visitStatuses = [
            ['Scheduled', 'scheduled'],
            ['Checked In', 'checked_in'],
            ['Checked Out', 'checked_out'],
            ['No Show', 'no_show'],
            ['Cancelled', 'cancelled'],
        ];

        $gatepassStmt = $this->db->prepare(
            'INSERT IGNORE INTO gatepass_statuses (tenant_id, name, code) VALUES (?, ?, ?)'
        );

        foreach ($gatepassStatuses as [$statusName, $statusCode]) {
            $gatepassStmt->execute([$tenantId, $statusName, $statusCode]);
        }

        $visitStmt = $this->db->prepare(
            'INSERT IGNORE INTO visit_statuses (tenant_id, name, code) VALUES (?, ?, ?)'
        );

        foreach ($visitStatuses as [$statusName, $statusCode]) {
            $visitStmt->execute([$tenantId, $statusName, $statusCode]);
        }

        $visitTypeStmt = $this->db->prepare(
            'INSERT IGNORE INTO visit_types (tenant_id, name) VALUES (?, ?)'
        );

        foreach (['Business Meeting', 'Interview', 'Delivery', 'Maintenance', 'Personal', 'Contractor Work'] as $type) {
            $visitTypeStmt->execute([$tenantId, $type]);
        }

        $idTypeStmt = $this->db->prepare(
            'INSERT IGNORE INTO identification_types (tenant_id, name) VALUES (?, ?)'
        );

        foreach (['National ID', 'Passport', 'Driving Licence', 'Company ID', 'Military ID'] as $type) {
            $idTypeStmt->execute([$tenantId, $type]);
        }
    }
}
