<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Settings\Repositories\GateRepository;

final class GateKeyAndDeviceTest extends DatabaseTestCase
{
    private const TENANT = 1;

    private GateRepository $gates;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gates = new GateRepository();
    }

    private function createGate(string $name, string $code, ?string $keyHash, bool $active = true): int
    {
        // '' inserts an empty string; the app never issues empty device keys.
        // Inactive gates are switched off via update() right after creation.
        $id = $this->gates->create(self::TENANT, $name, $code, $keyHash ?? '');

        if (!$active) {
            $this->gates->update(self::TENANT, $id, $name, $code, false);
        }

        return $id;
    }

    public function testFindByKeyHashResolvesActiveGate(): void
    {
        $key = 'gk_' . bin2hex(random_bytes(20));
        $id = $this->createGate('North Gate', 'NG', hash('sha256', $key));

        $gate = $this->gates->findByKeyHash(hash('sha256', $key));

        $this->assertNotNull($gate);
        $this->assertSame($id, (int) $gate['id']);
        $this->assertSame(self::TENANT, (int) $gate['tenant_id']);
        $this->assertSame('North Gate', $gate['name']);
    }

    public function testUnknownAndPlainHashLookupsReturnNull(): void
    {
        $this->createGate('South Gate', 'SG', hash('sha256', 'gk_deadbeef'));

        $this->assertNull($this->gates->findByKeyHash(hash('sha256', 'gk_wrongkey')));
        // plaintext key must never resolve — only the sha256 digest is stored
        $this->assertNull($this->gates->findByKeyHash('gk_deadbeef'));
    }

    public function testInactiveGateIsNotResolvableByKey(): void
    {
        $key = 'gk_' . bin2hex(random_bytes(20));
        $id = $this->createGate('Closed Gate', 'CG', hash('sha256', $key), active: false);

        $this->assertNull($this->gates->findByKeyHash(hash('sha256', $key)));
        $this->assertNull($this->gates->findActive(self::TENANT, $id));
    }

    public function testRotationInvalidatesOldKey(): void
    {
        $old = 'gk_' . bin2hex(random_bytes(20));
        $new = 'gk_' . bin2hex(random_bytes(20));
        $id = $this->createGate('Rotating Gate', 'RG', hash('sha256', $old));

        $this->assertTrue($this->gates->rotateKey(self::TENANT, $id, hash('sha256', $new)));
        $this->assertNull($this->gates->findByKeyHash(hash('sha256', $old)));
        $this->assertNotNull($this->gates->findByKeyHash(hash('sha256', $new)));
    }

    public function testGateWithScanHistoryIsDetected(): void
    {
        $id = $this->createGate('Logged Gate', 'LG', null);
        $this->db->exec("UPDATE gates SET key_hash = NULL WHERE id = {$id}");

        $this->assertFalse($this->gates->hasScanLogs(self::TENANT, $id));

        $this->db->prepare("
            INSERT INTO scan_logs (tenant_id, gate_id, subject_type, subject_id, action, source)
            VALUES (:tenant, :gate, 'gatepass', 0, 'rejected', 'device')
        ")->execute([':tenant' => self::TENANT, ':gate' => $id]);

        $this->assertTrue($this->gates->hasScanLogs(self::TENANT, $id));
    }

    public function testAllExposesHasKeyFlagWithoutLeakingHash(): void
    {
        $id = $this->createGate('Keyed Gate', 'KG', hash('sha256', 'gk_' . bin2hex(random_bytes(20))));
        $keylessId = $this->createGate('Open Gate', 'OG', null);
        $this->db->exec("UPDATE gates SET key_hash = NULL WHERE id = {$keylessId}");

        $all = $this->gates->all(self::TENANT);
        $byId = [];
        foreach ($all as $gate) {
            $byId[(int) $gate['id']] = $gate;
        }

        $this->assertSame(1, (int) $byId[$id]['has_key']);
        $this->assertSame(0, (int) $byId[$keylessId]['has_key']);
        $this->assertArrayNotHasKey('key_hash', $byId[$id], 'listing must not carry the raw hash');
    }
}
