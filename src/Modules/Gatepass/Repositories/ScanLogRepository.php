<?php
declare(strict_types=1);

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use PDO;
use Throwable;

final class ScanLogRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function record(
        int $tenantId,
        ?int $gateId,
        string $subjectType,
        int $subjectId,
        string $action,
        string $source,
        ?int $userId,
        ?string $detail = null
    ): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO scan_logs
                    (tenant_id, gate_id, subject_type, subject_id, action, source, user_id, detail)
                VALUES
                    (:tenant_id, :gate_id, :subject_type, :subject_id, :action, :source, :user_id, :detail)
            ");

            $stmt->execute([
                ':tenant_id'    => $tenantId,
                ':gate_id'      => $gateId,
                ':subject_type' => $subjectType,
                ':subject_id'   => $subjectId,
                ':action'       => $action,
                ':source'       => $source,
                ':user_id'      => $userId,
                ':detail'       => $detail !== null ? mb_substr($detail, 0, 255) : null,
            ]);
        } catch (Throwable) {
            // Scan logging is a side effect — never fail a scan because of it.
        }
    }
}
