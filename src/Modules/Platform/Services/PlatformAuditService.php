<?php

namespace App\Modules\Platform\Services;

use App\Core\DB;
use PDO;

class PlatformAuditService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    public static function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = []
    ): void {
        $user = $_SESSION['platform_user'] ?? null;

        try {
            $stmt = DB::connect()->prepare(
                'INSERT INTO platform_audit_logs
                    (platform_user_id, action, subject_type, subject_id, metadata, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );

            $stmt->execute([
                is_array($user) ? (int) ($user['id'] ?? 0) ?: null : null,
                $action,
                $subjectType,
                $subjectId,
                $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Never let audit bookkeeping break the action itself.
            error_log('[platform-audit] ' . $e->getMessage());
        }
    }

    public function recent(int $limit = 150): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $this->db->query(
            "SELECT l.id, l.action, l.subject_type, l.subject_id, l.metadata,
                    l.ip_address, l.created_at,
                    u.name AS platform_user_name, u.email AS platform_user_email
               FROM platform_audit_logs l
               LEFT JOIN platform_users u ON u.id = l.platform_user_id
              ORDER BY l.id DESC
              LIMIT {$limit}"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
