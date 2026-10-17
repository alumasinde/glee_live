<?php

namespace App\Modules\Visits\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Mailer;
use App\Modules\Visits\Repositories\VisitRepository;
use PDO;
use RuntimeException;

class PreRegistrationService
{
    private PDO $db;
    private VisitRepository $visits;

    public function __construct()
    {
        $this->db     = DB::connect();
        $this->visits = new VisitRepository();
    }

    public function findTenantByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, logo, email
            FROM tenants
            WHERE code = :code
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':code' => $code]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function formOptions(int $tenantId): array
    {
        $departments = $this->db->prepare("
            SELECT id, name
            FROM departments
            WHERE tenant_id = :tenant_id
              AND is_active = 1
            ORDER BY name
        ");
        $departments->execute([':tenant_id' => $tenantId]);

        $hosts = $this->db->prepare("
            SELECT id, first_name, last_name
            FROM users
            WHERE tenant_id = :tenant_id
              AND is_active = 1
            ORDER BY first_name, last_name
            LIMIT 300
        ");
        $hosts->execute([':tenant_id' => $tenantId]);

        return [
            'departments' => $departments->fetchAll(PDO::FETCH_ASSOC),
            'hosts'       => $hosts->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /**
     * @return array{outcome:string, visit_id:?int, token:?string}
     */
    public function register(int $tenantId, array $data): array
    {
        $visitor = $this->findOrCreateVisitor($tenantId, $data);

        if ((int) $visitor['is_blacklisted'] === 1) {
            Audit::system($tenantId, 'visit.pre_register_rejected', 'visitor', (int) $visitor['id'], [
                'reason' => 'blacklisted',
                'email'  => $data['email'],
            ]);

            return ['outcome' => 'rejected', 'visit_id' => null, 'token' => null];
        }

        $expectedDate = substr((string) $data['expected_in'], 0, 10);

        if ($this->visits->hasPendingVisit($tenantId, (int) $visitor['id'], $expectedDate)) {
            return ['outcome' => 'duplicate', 'visit_id' => null, 'token' => null];
        }

        $visitId = $this->visits->create([
            'tenant_id'       => $tenantId,
            'department_id'   => (int) $data['department_id'],
            'visitor_id'      => (int) $visitor['id'],
            'host_user_id'    => !empty($data['host_user_id']) ? (int) $data['host_user_id'] : null,
            'visit_type_id'   => null,
            'visit_status_id' => $this->scheduledStatusId($tenantId),
            'purpose'         => $data['purpose'],
            'expected_in'     => $this->toMysqlDatetime($data['expected_in']),
            'expected_out'    => !empty($data['expected_out']) ? $this->toMysqlDatetime($data['expected_out']) : null,
            'created_by'      => null,
        ]);

        $visit = $this->db->prepare("SELECT scan_token FROM visits WHERE id = :id LIMIT 1");
        $visit->bindValue(':id', $visitId, PDO::PARAM_INT);
        $visit->execute();
        $token = (string) $visit->fetchColumn();

        Audit::system($tenantId, 'visit.pre_registered', 'visit', $visitId, [
            'visitor_id'  => (int) $visitor['id'],
            'email'       => $data['email'],
            'expected_in' => $data['expected_in'],
        ]);

        $this->notify($tenantId, $visitor, $data, $token);

        return ['outcome' => 'created', 'visit_id' => $visitId, 'token' => $token];
    }

    public function findByToken(string $token): ?array
    {
        return $this->visits->findByTokenGlobal($token);
    }

    /* ------------------------------------------------------------------ */

    private function findOrCreateVisitor(int $tenantId, array $data): array
    {
        $stmt = $this->db->prepare("
            SELECT id, first_name, last_name, email, phone, is_blacklisted
            FROM visitors
            WHERE tenant_id = :tenant_id
              AND (
                    (email <> '' AND email = :email)
                 OR (:phone <> '' AND phone = :phone2)
                  )
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':email'     => strtolower(trim((string) $data['email'])),
            ':phone'     => trim((string) ($data['phone'] ?? '')),
            ':phone2'    => trim((string) ($data['phone'] ?? '')),
        ]);

        $found = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($found) {
            return $found;
        }

        $insert = $this->db->prepare("
            INSERT INTO visitors
                (tenant_id, first_name, last_name, email, phone, created_by)
            VALUES
                (:tenant_id, :first_name, :last_name, :email, :phone, 0)
        ");

        $insert->execute([
            ':tenant_id'  => $tenantId,
            ':first_name' => trim((string) $data['first_name']),
            ':last_name'  => trim((string) $data['last_name']),
            ':email'      => strtolower(trim((string) $data['email'])),
            ':phone'      => trim((string) ($data['phone'] ?? '')),
        ]);

        $id = (int) $this->db->lastInsertId();

        $read = $this->db->prepare("
            SELECT id, first_name, last_name, email, phone, is_blacklisted
            FROM visitors WHERE id = :id LIMIT 1
        ");
        $read->bindValue(':id', $id, PDO::PARAM_INT);
        $read->execute();

        return $read->fetch(PDO::FETCH_ASSOC);
    }

    private function scheduledStatusId(int $tenantId): int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM visit_statuses
            WHERE tenant_id = :tenant_id
              AND UPPER(code) = 'SCHEDULED'
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId]);
        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException('SCHEDULED visit status not configured for this tenant.');
        }

        return (int) $id;
    }

    private function toMysqlDatetime(string $value): string
    {
        $ts = strtotime($value);

        if ($ts === false) {
            throw new RuntimeException('Invalid date.');
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function notify(int $tenantId, array $visitor, array $data, string $token): void
    {
        $tenantName = $this->tenantName($tenantId);
        $passUrl    = $this->baseUrl() . '/pass/' . $token;

        $html = '<h2>Your visit to ' . htmlspecialchars($tenantName) . ' is registered</h2>'
            . '<p>Name: ' . htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']) . '</p>'
            . '<p>Arrival: ' . htmlspecialchars((string) $data['expected_in']) . '</p>'
            . '<p>Purpose: ' . htmlspecialchars((string) $data['purpose']) . '</p>'
            . '<p>On arrival, open your digital pass and show the QR code at the gate:</p>'
            . '<p><a href="' . htmlspecialchars($passUrl) . '">' . htmlspecialchars($passUrl) . '</a></p>';

        Mailer::send($tenantId, (string) $visitor['email'], 'Your visit pass — ' . $tenantName, $html);

        if (!empty($data['host_user_id'])) {
            $host = $this->db->prepare("SELECT first_name, last_name, email FROM users WHERE id = :id LIMIT 1");
            $host->bindValue(':id', (int) $data['host_user_id'], PDO::PARAM_INT);
            $host->execute();
            $hostUser = $host->fetch(PDO::FETCH_ASSOC);

            if ($hostUser && !empty($hostUser['email'])) {
                Mailer::send(
                    $tenantId,
                    (string) $hostUser['email'],
                    'Pre-registered visitor: ' . $visitor['first_name'] . ' ' . $visitor['last_name'],
                    '<p>' . htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name'])
                        . ' pre-registered to visit you on '
                        . htmlspecialchars((string) $data['expected_in']) . '.</p>'
                        . '<p>Purpose: ' . htmlspecialchars((string) $data['purpose']) . '</p>'
                );
            }
        }
    }

    private function tenantName(int $tenantId): string
    {
        $stmt = $this->db->prepare("SELECT name FROM tenants WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();

        return (string) ($stmt->fetchColumn() ?: 'our site');
    }

    private function baseUrl(): string
    {
        $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
