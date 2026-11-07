<?php

namespace App\Core;

use App\Core\DB;
use App\Modules\Audit\Repositories\AuditLogRepository;
use App\Modules\Audit\Services\AuditService;
use Throwable;

class Audit
{
    /**
     * Log an action performed by the currently authenticated user.
     *
     * @param string      $action
     * @param string|null $entityType
     * @param int|null    $entityId
     * @param array|null  $metadata   Optional extra context.
     *                                Use ['target_label' => '…'] to fill the {target}
     *                                placeholder in the human-readable message.
     */
    public static function log(
        string  $action,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null
    ): void {
        try {
            if (empty($_SESSION['user'])) {
                return;
            }

            $user      = $_SESSION['user'];
            $actorName = self::resolveActorName($user);

            self::service()->log(
                (int) $user['tenant_id'],
                (int) $user['id'],
                $actorName,
                $action,
                $entityType,
                $entityId,
                self::withRequestContext($metadata),
                self::clientIp()
            );
        } catch (Throwable $e) {
            error_log('[Audit] log() failed: ' . $e->getMessage());
        }
    }
        /**
     * Log a system-initiated action (no human actor, e.g. scheduled jobs).
     *
     * @param int         $tenantId
     * @param string      $action
     * @param string|null $entityType
     * @param int|null    $entityId
     * @param array|null  $metadata
     */
    public static function system(
        int     $tenantId,
        string  $action,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null
    ): void {
        try {
            self::service()->log(
                $tenantId,
                null,          // no user
                'System',
                $action,
                $entityType,
                $entityId,
                self::withRequestContext($metadata),
                self::clientIp()
            );
        } catch (Throwable $e) {
            error_log('[Audit] system() failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function service(): AuditService
    {
        $db   = DB::connect();
        $repo = new AuditLogRepository($db);

        return new AuditService($repo);
    }

    /**
     * Build a readable actor name from the session user array.
     * Falls back gracefully if name fields are absent.
     */
    private static function resolveActorName(array $user): string
    {
        // Try full name first, then first+last, then email, then ID
        if (!empty($user['name'])) {
            return $user['name'];
        }

        $parts = array_filter([
            $user['first_name'] ?? '',
            $user['last_name']  ?? '',
        ]);

        if ($parts) {
            return implode(' ', $parts);
        }

        return $user['email'] ?? ('User #' . ($user['id'] ?? '?'));
    }
    private static function withRequestContext(?array $metadata): ?array
    {
        $context = array_filter([
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'url'    => $_SERVER['REQUEST_URI']    ?? null,
        ]);

        if (empty($metadata) && empty($context)) {
            return null;
        }

        return array_merge($metadata ?? [], ['_request' => $context ?: null]);
    }

    /**
     * The client IP, trusting forwarding headers only when the direct peer is
     * a configured proxy.
     *
     * HTTP_X_FORWARDED_FOR is client-writable, so on a direct connection it
     * would let any caller attribute an action to any address. Only when
     * REMOTE_ADDR is listed in TRUSTED_PROXIES is the chain read, and then
     * right-to-left: the rightmost entry was appended by the hop we trust.
     */
    private static function clientIp(): ?string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($remote === null || $remote === '') {
            return null;
        }

        $trusted = config('security.trusted_proxies') ?? [];

        if (!is_array($trusted) || !in_array($remote, $trusted, true)) {
            return $remote;
        }

        $chain = [];

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $chain[] = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            foreach (explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']) as $hop) {
                $chain[] = trim($hop);
            }
        }

        foreach (array_reverse($chain) as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            if (in_array($candidate, $trusted, true)) {
                continue;
            }

            return $candidate;
        }

        return $remote;
    }
}