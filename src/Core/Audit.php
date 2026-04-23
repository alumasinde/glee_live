<?php

namespace App\Core;

use App\Core\DB;
use App\Modules\Audit\Repositories\AuditLogRepository;
use App\Modules\Audit\Services\AuditService;
use Throwable;

/**
 * Audit – static facade for application-wide audit logging.
 *
 * Usage (user action):
 *   Audit::log('gatepass.approved', 'gatepass', $gatepassId, ['target_label' => "GP-00123"]);
 *
 * Usage (system / background job):
 *   Audit::system($tenantId, 'system.backup_completed');
 *
 * The facade resolves the acting user's name from the session so callers
 * never have to pass it manually.
 */
class Audit
{
    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

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

    /**
     * Append HTTP request context to metadata.
     * Only stored once (in metadata) — NOT also in a separate column.
     *
     * Returns null if there is nothing meaningful to store, so rows with
     * no business metadata don't get a JSON blob just for context fields.
     */
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
     * Resolve the real client IP, honouring common proxy headers.
     */
    private static function clientIp(): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                // X-Forwarded-For can be a comma-separated list; take the first
                return explode(',', $_SERVER[$key])[0];
            }
        }

        return null;
    }
}