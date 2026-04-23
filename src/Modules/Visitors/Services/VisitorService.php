<?php

declare(strict_types=1);

namespace App\Modules\Visitors\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Visitors\Repositories\VisitorRepository;
use App\Modules\Visitors\Services\RiskEngine;
use App\Modules\Visitors\DTOs\VisitorDTO;
use PDO;
use Exception;

final class VisitorService
{
    private VisitorRepository $repo;
    private RiskEngine $riskEngine;
    private PDO $db;

    public function __construct()
    {
        $this->repo = new VisitorRepository();
        $this->db   = DB::connect();
        $this->riskEngine = new RiskEngine();        
    }

//CREATE VISITOR
    public function create(VisitorDTO $dto): int
{
    if ($dto->id_number) {
        $existing = $this->repo->findByIdNumber(
            $dto->tenant_id,
            $dto->id_number
        );

        if ($existing) {
            throw new Exception(
                'A visitor with this ID number already exists.'
            );
        }
    }

    $this->db->beginTransaction();

    try {

        $companyId = $dto->company_id;

        if ($dto->hasNewCompany()) {
            $companyId = $this->repo->createCompany(
                $dto->tenant_id,
                $dto->new_company_name
            );
        }

        // Use RiskEngine
        $riskScore = $this->riskEngine->calculate(
            $dto->id_number,
            $dto->phone,
            false,
            0
        );

        $visitorId = $this->repo->create([
            'tenant_id'      => $dto->tenant_id,
            'first_name'     => $dto->first_name,
            'last_name'      => $dto->last_name,
            'id_type_id'     => $dto->id_type_id,
            'id_number'      => $dto->id_number,
            'phone'          => $dto->phone,
            'email'          => $dto->email,
            'company_id'     => $companyId,
            'risk_score'     => $riskScore,
            'is_blacklisted' => 0
        ]);

        // Watchlist check
        if ($this->repo->isOnWatchlist(
            $dto->tenant_id,
            $visitorId
        )) {

            $updatedRisk = $this->riskEngine->calculate(
                $dto->id_number,
                $dto->phone,
                true,
                0
            );

            $this->repo->blacklist(
                $dto->tenant_id,
                $visitorId,
                $updatedRisk
            );
        }

        Audit::log(
            'visitor.created',
            'visitor',
            $visitorId,
            [
                'tenant_id' => $dto->tenant_id,
                'id_number' => $dto->id_number,
                'name'      => $dto->fullName()
            ]
        );

        $this->db->commit();

        return $visitorId;

    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}

// UPDATE VISITOR
    public function update(
    int $tenantId,
    int $visitorId,
    VisitorDTO $dto
): void {

    $this->db->beginTransaction();

    try {

        $existing = $this->repo->find($tenantId, $visitorId);

        if (!$existing) {
            throw new Exception('Visitor not found.');
        }

        if ($dto->id_number) {
            $existingById = $this->repo->findByIdNumber(
                $tenantId,
                $dto->id_number
            );

            if ($existingById && (int)$existingById['id'] !== $visitorId) {
                throw new Exception(
                    'Another visitor already uses this ID number.'
                );
            }
        }

        $companyId = $dto->company_id;

        if ($dto->hasNewCompany()) {
            $companyId = $this->repo->createCompany(
                $tenantId,
                $dto->new_company_name
            );
        }

        $previousVisits = $this->repo->countVisits($tenantId, $visitorId);

        $riskScore = $this->riskEngine->calculate(
            $dto->id_number,
            $dto->phone,
            (bool)$existing['is_blacklisted'],
            $previousVisits
        );

        $this->repo->update($tenantId, $visitorId, [
            'first_name' => $dto->first_name,
            'last_name'  => $dto->last_name,
            'id_type_id' => $dto->id_type_id,
            'id_number'  => $dto->id_number,
            'phone'      => $dto->phone,
            'email'      => $dto->email,
            'company_id' => $companyId,
            'risk_score' => $riskScore,
        ]);

        Audit::log(
            'visitor.updated',
            'visitor',
            $visitorId,
            ['tenant_id' => $tenantId]
        );

        $this->db->commit();

    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}
    /*
    |--------------------------------------------------------------------------
    | BLACKLIST
    |--------------------------------------------------------------------------
    */
    public function blacklist(
        int $tenantId,
        int $visitorId,
        int $riskScore = 100
    ): void {

        $existing = $this->repo->find($tenantId, $visitorId);

        if (!$existing) {
            throw new Exception('Visitor not found.');
        }

        $this->repo->blacklist(
            $tenantId,
            $visitorId,
            $riskScore
        );

        Audit::log(
            'visitor.blacklisted',
            'visitor',
            $visitorId,
            ['tenant_id' => $tenantId]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UNBLACKLIST
    |--------------------------------------------------------------------------
    */
    public function unblacklist(
        int $tenantId,
        int $visitorId
    ): void {

        $existing = $this->repo->find($tenantId, $visitorId);

        if (!$existing) {
            throw new Exception('Visitor not found.');
        }

        $this->repo->unblacklist(
            $tenantId,
            $visitorId
        );

        Audit::log(
            'visitor.unblacklisted',
            'visitor',
            $visitorId,
            ['tenant_id' => $tenantId]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | LIST VISITORS
    |--------------------------------------------------------------------------
    */

    public function find(int $tenantId, int $visitorId): ?array
{
    return $this->repo->find($tenantId, $visitorId);
}
    public function list(int $tenantId): array
    {
        return $this->repo->allWithRelations($tenantId);
    }

public function findWithVisits(int $tenantId, int $visitorId): array
{
    return $this->repo->findWithVisits($tenantId, $visitorId);
}

    public function getIdentificationTypes(int $tenantId): array
    {
        return $this->repo->getIdentificationTypes($tenantId);
    }

    public function getCompanies(int $tenantId): array
    {
        return $this->repo->getCompanies($tenantId);
    }
}