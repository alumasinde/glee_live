<?php

namespace App\Modules\Settings\Services;

use App\Core\Audit;
use App\Core\Tenant;
use App\Modules\Settings\Repositories\GateRepository;
use PDOException;
use RuntimeException;

class GateService
{
    public function __construct(
        private GateRepository $repo
    ) {}

    public function all(): array
    {
        return $this->repo->all(Tenant::require());
    }

    /**
     * @return array{id: int, key: string} the plaintext key is shown once
     */
    public function create(string $name, string $code): array
    {
        $tenantId = Tenant::require();

        $this->validate($name, $code);

        $key = 'gk_' . bin2hex(random_bytes(20));

        try {
            $id = $this->repo->create($tenantId, $name, strtoupper($code), hash('sha256', $key));
        } catch (PDOException $e) {
            throw new RuntimeException($this->duplicateMessage($e), 0, $e);
        }

        Audit::log('gate.created', 'gate', $id, ['name' => $name, 'code' => $code]);

        return ['id' => $id, 'key' => $key];
    }

    public function update(int $id, string $name, string $code, bool $isActive): void
    {
        $tenantId = Tenant::require();

        $this->validate($name, $code);

        try {
            $updated = $this->repo->update($tenantId, $id, $name, strtoupper($code), $isActive);
        } catch (PDOException $e) {
            throw new RuntimeException($this->duplicateMessage($e), 0, $e);
        }

        if (!$updated) {
            throw new RuntimeException('Gate not found or no changes were made.');
        }

        Audit::log('gate.updated', 'gate', $id, ['name' => $name, 'code' => $code, 'is_active' => $isActive]);
    }

    /**
     * @return string the new plaintext key, shown once
     */
    public function rotateKey(int $id): string
    {
        $tenantId = Tenant::require();

        $key = 'gk_' . bin2hex(random_bytes(20));

        if (!$this->repo->rotateKey($tenantId, $id, hash('sha256', $key))) {
            throw new RuntimeException('Gate not found.');
        }

        Audit::log('gate.key_rotated', 'gate', $id);

        return $key;
    }

    public function delete(int $id): void
    {
        $tenantId = Tenant::require();

        if ($this->repo->hasScanLogs($tenantId, $id)) {
            throw new RuntimeException('Gate has scan history — deactivate it instead of deleting.');
        }

        if (!$this->repo->delete($tenantId, $id)) {
            throw new RuntimeException('Gate not found.');
        }

        Audit::log('gate.deleted', 'gate', $id);
    }

    private function validate(string $name, string $code): void
    {
        $name = trim($name);
        $code = trim($code);

        if ($name === '' || mb_strlen($name) > 120) {
            throw new RuntimeException('Gate name is required (max 120 characters).');
        }

        if (!preg_match('/^[A-Za-z0-9_-]{2,20}$/', $code)) {
            throw new RuntimeException('Gate code must be 2-20 letters, numbers, hyphens or underscores.');
        }
    }

    private function duplicateMessage(PDOException $e): string
    {
        return ($e->getCode() === '23000')
            ? 'A gate with that name or code already exists.'
            : 'Gate operation failed: ' . ($e->errorInfo[1] ?? $e->getMessage());
    }
}
