<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Gatepass\Repositories\GatepassRepository;
use App\Modules\Gatepass\Services\GatepassService;
use App\Modules\Settings\Repositories\GatepassTypeRepository;
use App\Modules\Settings\Services\GatepassTypeService;
use RuntimeException;

/**
 * Gatepass type configuration: the returnable default and the three gate
 * actions (check-in / check-out / return).
 *
 * Regression cover for the flags that used to be unreadable or unwritable:
 * the list read a column the table did not have, the save wrote only
 * allowed_actions, and the return scan was the one transition with no
 * type-level check.
 */
final class GatepassTypeConfigTest extends DatabaseTestCase
{
    private const TENANT = 1;

    private GatepassTypeRepository $repo;
    private GatepassTypeService $service;
    private string $suffix;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION['user'] = ['id' => 1, 'tenant_id' => self::TENANT];

        $this->repo = new GatepassTypeRepository();
        $this->service = new GatepassTypeService($this->repo);
        $this->suffix = strtoupper(bin2hex(random_bytes(3)));
    }

    protected function tearDown(): void
    {
        unset($_SESSION['user']);

        parent::tearDown();
    }

    private function makeType(?string $actions = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO gatepass_types (tenant_id, name, type_code, is_active, allowed_actions)
             VALUES (?, ?, ?, 1, ?)"
        );
        $stmt->execute([self::TENANT, 'Cfg Type ' . $this->suffix, 'CFG' . $this->suffix, $actions]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, int> $overrides */
    private function makeReturnablePass(int $typeId, array $overrides = []): int
    {
        $row = array_merge([
            'tenant_id'            => self::TENANT,
            'gatepass_number'      => 'CFG-' . $this->suffix,
            'scan_token'           => $this->newToken(),
            'gatepass_type_id'     => $typeId,
            'status_id'            => $this->gatepassStatusId(self::TENANT, 'checked_in'),
            'purpose'              => 'configuration test',
            'needs_approval'       => 0,
            'is_returnable'        => 1,
            'expected_return_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'actual_in'            => date('Y-m-d H:i:s'),
            'created_by'           => 1,
        ], $overrides);

        $cols = array_keys($row);
        $stmt = $this->db->prepare(sprintf(
            'INSERT INTO gatepasses (%s) VALUES (%s)',
            implode(', ', array_map(fn (string $c): string => "`{$c}`", $cols)),
            implode(', ', array_map(fn (string $c): string => ":{$c}", $cols))
        ));
        $stmt->execute($row);

        return (int) $this->db->lastInsertId();
    }

    public function testSaveWritesReturnableDefaultAndAllThreeActions(): void
    {
        $typeId = $this->makeType();

        $this->service->updateConfig($typeId, true, false, true, true);

        $type = $this->repo->find($typeId, self::TENANT);

        $this->assertNotNull($type);
        $this->assertTrue($type->isReturnable);
        $this->assertTrue($type->hasActionConfig());
        $this->assertTrue($type->allowsAction('checkin'));
        $this->assertFalse($type->allowsAction('checkout'));
        $this->assertTrue($type->allowsAction('return'));

        $stored = $this->db->query("SELECT allowed_actions FROM gatepass_types WHERE id = {$typeId}")->fetchColumn();
        $this->assertJson((string) $stored);
        $this->assertStringContainsString('"return"', (string) $stored);
    }

    public function testTurningTheReturnableDefaultBackOffPersists(): void
    {
        $typeId = $this->makeType();
        $this->service->updateConfig($typeId, true, true, true, true);

        $this->service->updateConfig($typeId, true, true, false, false);

        $type = $this->repo->find($typeId, self::TENANT);

        $this->assertFalse($type->isReturnable);
        $this->assertFalse($type->allowsAction('return'));
    }

    public function testOmittedReturnableKeyLeavesTheStoredFlagAlone(): void
    {
        $typeId = $this->makeType();
        $this->service->updateConfig($typeId, true, true, true, true);

        // A client that knows nothing about the new switch must not reset it.
        $this->service->updateConfig($typeId, false, false, false, null);

        $this->assertTrue($this->repo->find($typeId, self::TENANT)->isReturnable);
    }

    public function testUnconfiguredTypeReportsEveryActionAllowed(): void
    {
        $type = $this->repo->find($this->makeType(), self::TENANT);

        $this->assertNotNull($type);
        $this->assertFalse($type->hasActionConfig());
        $this->assertTrue($type->allowsAction('checkin'));
        $this->assertTrue($type->allowsAction('checkout'));
        $this->assertTrue($type->allowsAction('return'));
        $this->assertFalse($type->isReturnable);
    }

    public function testReadsExposeTheCodeColumnConsistently(): void
    {
        $typeId = $this->makeType();

        $this->service->updateConfig($typeId, true, true, true, false);

        // all() and find() must agree: both alias type_code AS code.
        $fromList = null;
        foreach ($this->repo->all() as $candidate) {
            if ($candidate->id === $typeId) {
                $fromList = $candidate;
            }
        }

        $this->assertNotNull($fromList, 'the new type must appear in all()');
        $this->assertSame('CFG' . $this->suffix, $fromList->code);
        $this->assertSame($fromList->code, $this->repo->find($typeId, self::TENANT)->code);
    }

    public function testReturnScanBlockedWhenTypeDisallowsIt(): void
    {
        $typeId = $this->makeType('{"checkin": true, "checkout": true, "return": false}');
        $passId = $this->makeReturnablePass($typeId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not permitted');

        (new GatepassService())->markReturned(self::TENANT, $passId);
    }

    public function testSilentAllowedActionsStillLetsTheReturnScanThrough(): void
    {
        $typeId = $this->makeType();
        $passId = $this->makeReturnablePass($typeId);

        $this->assertTrue((new GatepassService())->markReturned(self::TENANT, $passId));

        $row = (new GatepassRepository())->findById(self::TENANT, $passId);
        $this->assertSame('returned', strtolower((string) $row['status_code']));
    }

    public function testNonReturnablePassCannotBeReturnedEvenWhenTypeAllowsIt(): void
    {
        $typeId = $this->makeType('{"checkin": true, "checkout": true, "return": true}');
        $passId = $this->makeReturnablePass($typeId, ['is_returnable' => 0]);

        try {
            (new GatepassService())->markReturned(self::TENANT, $passId);
            $this->fail('a pass the requester did not mark returnable must not return');
        } catch (RuntimeException $e) {
            $this->assertSame('Gatepass is not returnable.', $e->getMessage());
        }
    }

    public function testReturnStampsTheBookkeepingAndCannotRepeat(): void
    {
        $typeId = $this->makeType();
        $passId = $this->makeReturnablePass($typeId);

        $this->db->prepare('
            INSERT INTO gatepass_items (tenant_id, gatepass_id, item_name, quantity, is_returnable, returned_quantity)
            VALUES (:tenant_id, :gatepass_id, :item_name, 2, 1, 0)
        ')->execute([':tenant_id' => self::TENANT, ':gatepass_id' => $passId, ':item_name' => 'Projector']);

        (new GatepassService())->markReturned(self::TENANT, $passId);

        $pass = (new GatepassRepository())->findById(self::TENANT, $passId);
        $this->assertNotEmpty($pass['actual_return_date'], 'a gate return has to be dated');
        $this->assertSame(1, (int) $pass['is_fully_returned']);

        $returned = $this->db->query("SELECT returned_quantity FROM gatepass_items WHERE gatepass_id = {$passId}")->fetchColumn();
        $this->assertSame('2', (string) $returned, 'the items have to be closed out with the pass');

        try {
            (new GatepassService())->markReturned(self::TENANT, $passId);
            $this->fail('a returned pass must not be returned again');
        } catch (RuntimeException $e) {
            $this->assertSame('Gatepass already returned.', $e->getMessage());
        }
    }
}
