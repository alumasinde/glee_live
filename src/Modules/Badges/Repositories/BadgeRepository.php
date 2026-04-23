<?php

declare(strict_types=1);

namespace App\Modules\Badges\Repositories;

use App\Core\DB;
use PDO;

final class BadgeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUE BADGE
    |--------------------------------------------------------------------------
    */
    public function issue(
        int $tenantId,
        int $visitId,
        string $badgeCode
    ): int {

        // Deactivate any previous active badge
        $this->db->prepare("
            UPDATE visit_badges
            SET is_active = 0,
                returned_at = NOW()
            WHERE tenant_id = :tenant_id
              AND visit_id = :visit_id
              AND is_active = 1
        ")->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO visit_badges (
                tenant_id,
                visit_id,
                badge_code,
                printed_at,
                is_active
            )
            VALUES (
                :tenant_id,
                :visit_id,
                :badge_code,
                NOW(),
                1
            )
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId,
            ':badge_code'=> $badgeCode
        ]);

        return (int) $this->db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN BADGE
    |--------------------------------------------------------------------------
    */
    public function returnActiveBadge(
        int $tenantId,
        int $visitId
    ): void {
        $stmt = $this->db->prepare("
            UPDATE visit_badges
            SET is_active = 0,
                returned_at = NOW()
            WHERE tenant_id = :tenant_id
              AND visit_id = :visit_id
              AND is_active = 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId
        ]);
    }
 public function hasActiveBadge(int $tenantId, int $visitId): bool
{
    $sql = "
        SELECT 1
        FROM visit_badges
        WHERE tenant_id = :tenant_id
          AND visit_id  = :visit_id
          AND is_active = 1
          AND returned_at IS NULL
        LIMIT 1
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':visit_id'  => $visitId,
    ]);

    return (bool) $stmt->fetchColumn();
}
    /*
    |--------------------------------------------------------------------------
    | FIND ACTIVE BADGE
    |--------------------------------------------------------------------------
    */
    public function findActiveByVisit(
        int $tenantId,
        int $visitId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM visit_badges
            WHERE tenant_id = :tenant_id
              AND visit_id = :visit_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}