<?php

namespace App\Modules\Platform\Repositories;

use App\Core\DB;
use PDO;

class PlatformUserRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    public function findActiveByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password_hash, is_active
               FROM platform_users
              WHERE email = :email
                AND is_active = 1
              LIMIT 1'
        );
        $stmt->execute([':email' => strtolower(trim($email))]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updatePasswordHash(int $id, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE platform_users SET password_hash = ? WHERE id = ?');
        $stmt->execute([$hash, $id]);
    }

    public function touchLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE platform_users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }
}
