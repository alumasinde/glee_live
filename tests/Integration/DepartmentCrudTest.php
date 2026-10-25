<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Settings\Repositories\DepartmentRepository;
use App\Modules\Settings\Services\DepartmentService;
use RuntimeException;

/**
 * Department settings CRUD.
 *
 * Regression cover for the two defects found in review: a save that changes
 * nothing reported failure (MariaDB returns rowCount 0), and the department
 * code read from the edit form was never written to the row.
 *
 * Codes carry a random suffix because uk_dept_tenant_code is a real unique key
 * and the scratch schema already ships reference departments (FIN, HR, IT...).
 */
final class DepartmentCrudTest extends DatabaseTestCase
{
    private const TENANT = 1;

    private DepartmentService $service;
    private DepartmentRepository $repo;
    private string $suffix;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION['user'] = ['id' => 1, 'tenant_id' => self::TENANT];

        $this->repo = new DepartmentRepository();
        $this->service = new DepartmentService($this->repo);
        $this->suffix = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);

        parent::tearDown();
    }

    private function code(string $prefix): string
    {
        return $prefix . $this->suffix;
    }

    public function testUnchangedSaveStillSucceeds(): void
    {
        $created = $this->service->create('Facilities', $this->code('FAC'));

        $updated = $this->service->update($created->id, 'Facilities', $created->code);

        $this->assertNotNull($updated, 'a no-op save must not look like a failure');
        $this->assertSame('Facilities', $updated->name);
    }

    public function testCodeIsPersistedOnUpdate(): void
    {
        $created = $this->service->create('Logistics', $this->code('LOG'));

        $renamed = $this->code('LGS');
        $updated = $this->service->update($created->id, 'Logistics', $renamed);

        $this->assertSame($renamed, $updated->code);
        $this->assertSame($renamed, $this->repo->find($created->id, self::TENANT)->code);
    }

    public function testUpdateRejectsUnknownDepartment(): void
    {
        $created = $this->service->create('Records', $this->code('REC'));

        try {
            $this->service->update($created->id + 9999, 'Whatever', $this->code('WV'));
            $this->fail('updating a missing department must throw');
        } catch (RuntimeException $e) {
            $this->assertSame('Department not found.', $e->getMessage());
        }
    }

    public function testUpdateValidatesBothFields(): void
    {
        $created = $this->service->create('Training', $this->code('TRN'));

        foreach ([['', $created->code], ['Training', '']] as [$name, $code]) {
            try {
                $this->service->update($created->id, $name, $code);
                $this->fail('validation should reject ' . var_export($name === '' ? 'name' : 'code', true));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('required', $e->getMessage());
            }
        }

        $this->assertSame($this->code('TRN'), $this->repo->find($created->id, self::TENANT)->code);
    }

    public function testDuplicateCodeIsRefusedWithAReadableMessage(): void
    {
        $taken = $this->service->create('Alpha Unit', $this->code('A1'));

        try {
            $this->service->create('Beta Unit', $taken->code);
            $this->fail('a duplicate code must be refused');
        } catch (RuntimeException $e) {
            $this->assertSame('Department code is already in use.', $e->getMessage());
        }

        $other = $this->service->create('Gamma Unit', $this->code('G1'));

        try {
            $this->service->update($other->id, 'Gamma Unit', $taken->code);
            $this->fail('renaming onto a duplicate code must be refused');
        } catch (RuntimeException $e) {
            $this->assertSame('Department code is already in use.', $e->getMessage());
        }

        $this->assertSame($this->code('G1'), $this->repo->find($other->id, self::TENANT)->code);
    }

    public function testToggleFlipsStatusTwice(): void
    {
        $created = $this->service->create('Front Office', $this->code('FO'));

        $off = $this->service->toggle($created->id);
        $this->assertFalse($off->isActive);

        $on = $this->service->toggle($created->id);
        $this->assertTrue($on->isActive);
    }

    public function testDepartmentsAreTenantScoped(): void
    {
        $created = $this->service->create('Guard Room', $this->code('GRD'));

        $_SESSION['user']['tenant_id'] = 2;

        try {
            $this->service->update($created->id, 'Renamed', $created->code);
            $this->fail('cross-tenant update must be refused');
        } catch (RuntimeException $e) {
            $this->assertSame('Department not found.', $e->getMessage());
        }

        $this->assertSame('Guard Room', $this->repo->find($created->id, self::TENANT)->name);
    }
}
