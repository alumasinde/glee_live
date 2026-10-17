<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\DB;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $db;

    public static function setUpBeforeClass(): void
    {
        $name = $_ENV['DB_DATABASE'];

        $exists = DB::connect()
            ->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$name}'")
            ->fetchColumn();

        if ($exists === false) {
            throw new RuntimeException(
                "Test database '{$name}' missing. Run: php bin/migrate.php up --db={$name}"
            );
        }
    }

    protected function setUp(): void
    {
        $this->db = DB::connect();
        $this->db->beginTransaction();

        $this->seedTenantFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    /**
     * Boot rows the scan flows depend on. Tenant 1 comes from the 0010 seed
     * (or is created here); tenant 2 is cloned from tenant 1's reference rows.
     * Status/type/department ids are always looked up by code, never hardcoded.
     */
    private function seedTenantFixtures(): void
    {
        $this->db->exec("INSERT IGNORE INTO tenants (id, name, code, logo, email, phone)
            VALUES (1, 'Test Tenant', 'test', '', 'test@test.local', '+000000000'),
                   (2, 'Other Tenant', 'other', '', 'other@test.local', '+000000000')");

        // Safety net when the scratch DB was migrated without the 0010 boot seed.
        $this->db->exec("INSERT IGNORE INTO gatepass_statuses (tenant_id, name, code) VALUES
            (1, 'Pending', 'pending'), (1, 'Approved', 'approved'), (1, 'Rejected', 'rejected'),
            (1, 'Checked In', 'checked_in'), (1, 'Checked Out', 'checked_out'),
            (1, 'Cancelled', 'cancelled'), (1, 'Expired', 'expired'), (1, 'Returned', 'returned'),
            (2, 'Pending', 'pending'), (2, 'Approved', 'approved'), (2, 'Rejected', 'rejected'),
            (2, 'Checked In', 'checked_in'), (2, 'Checked Out', 'checked_out'),
            (2, 'Cancelled', 'cancelled'), (2, 'Expired', 'expired'), (2, 'Returned', 'returned')");
        $this->db->exec("INSERT IGNORE INTO visit_statuses (tenant_id, name, code) VALUES
            (1, 'Scheduled', 'scheduled'), (1, 'Checked In', 'checked_in'), (1, 'Checked Out', 'checked_out'),
            (1, 'No Show', 'no_show'), (1, 'Cancelled', 'cancelled'),
            (2, 'Scheduled', 'scheduled'), (2, 'Checked In', 'checked_in'), (2, 'Checked Out', 'checked_out'),
            (2, 'No Show', 'no_show'), (2, 'Cancelled', 'cancelled')");

        $this->db->exec("
            INSERT IGNORE INTO gatepass_statuses (tenant_id, name, code)
            SELECT 2, name, code FROM gatepass_statuses WHERE tenant_id = 1");

        $this->db->exec("
            INSERT IGNORE INTO visit_statuses (tenant_id, name, code)
            SELECT 2, name, code FROM visit_statuses WHERE tenant_id = 1");

        $this->db->exec("
            INSERT IGNORE INTO departments (tenant_id, name, code)
            SELECT 2, name, code FROM departments WHERE tenant_id = 1");

        $this->db->exec("
            INSERT IGNORE INTO gatepass_types (tenant_id, name, type_code, is_active)
            SELECT 2, name, type_code, is_active FROM gatepass_types WHERE tenant_id = 1");

        $this->db->exec("INSERT IGNORE INTO users (id, tenant_id, email, password_hash, first_name, last_name, username, is_admin)
            VALUES (1, 1, 'scan.user@test.local', 'x', 'Scan', 'User', 'scanuser', 0)");
    }

    protected function gatepassStatusId(int $tenantId, string $code): int
    {
        return $this->statusId('gatepass_statuses', $tenantId, $code);
    }

    protected function visitStatusId(int $tenantId, string $code): int
    {
        return $this->statusId('visit_statuses', $tenantId, $code);
    }

    protected function statusId(string $table, int $tenantId, string $code): int
    {
        $stmt = $this->db->prepare("SELECT id FROM `{$table}` WHERE tenant_id = ? AND LOWER(code) = LOWER(?) LIMIT 1");
        $stmt->execute([$tenantId, $code]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("Reference row {$table}/{$code} missing for tenant {$tenantId}.");
        }

        return (int) $id;
    }

    /**
     * @param array<int, string> $columns
     * @param array<int, array<int, mixed>> $rows
     */
    protected function insertIgnoreRows(string $table, array $columns, array $rows): void
    {
        $cols = [];
        $holds = [];
        foreach ($columns as $c) {
            $cols[]  = "`{$c}`";
            $holds[] = ":{$c}";
        }

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO `{$table}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $holds) . ")"
        );

        foreach ($rows as $row) {
            $params = [];
            foreach ($columns as $i => $c) {
                $params[":{$c}"] = $row[$i];
            }
            $stmt->execute($params);
        }
    }

    protected function newToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
