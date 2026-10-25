<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Repositories\AuditLogRepository;
use Throwable;

/**
 * AuditService
 *
 * Thin orchestration layer between callers and the repository.
 * Responsible for:
 *   - Generating human-readable log messages from action + actor name
 *   - Resolving the client IP
 *   - Swallowing exceptions so audit failures never break business logic
 */
class AuditService
{
    /**
     * Map of machine-readable actions to human-readable sentence templates.
     *
     * Placeholders:
     *   {actor}  – the acting user's full name (or "System")
     *   {target} – optional target entity label passed in metadata['target_label']
     *
     * Add new actions here as your domain grows.
     */
    private const MESSAGE_TEMPLATES = [
        // Auth
        'user.login'              => '{actor} logged in',
        'user.logout'             => '{actor} logged out',
        'user.login_failed'       => 'Failed login attempt for {actor}',
        'user.password_changed'   => '{actor} changed their password',
        'user.password_reset'     => 'Password reset requested for {actor}',
        'user.mfa_enabled'        => '{actor} enabled two-factor authentication',
        'user.mfa_disabled'       => '{actor} disabled two-factor authentication',

        // Dashboard
        'dashboard.viewed'        => '{actor} viewed the dashboard',

        // User management
        'user.created'            => '{actor} created user account for {target}',
        'user.updated'            => '{actor} updated user account for {target}',
        'user.deleted'            => '{actor} deleted user account for {target}',
        'user.role_changed'       => '{actor} changed role for {target}',
        'user.deactivated'        => '{actor} deactivated account for {target}',
        'user.reactivated'        => '{actor} reactivated account for {target}',

        // Gatepass
        'gatepass.viewed'        => '{actor} viewed gatepass {target}',
        'gatepass.list_viewed'   => '{actor} viewed the gatepass list',
        'gatepass.created'        => '{actor} created gatepass {target}',
        'gatepass.updated'        => '{actor} updated gatepass {target}',
        'gatepass.submitted'      => '{actor} submitted gatepass {target} for approval',
        'gatepass.approved'       => '{actor} approved gatepass {target}',
        'gatepass.rejected'       => '{actor} rejected gatepass {target}',
        'gatepass.cancelled'      => '{actor} cancelled gatepass {target}',
        'gatepass.deleted'        => '{actor} deleted gatepass {target}',
        'gatepass.checked_in'     => '{actor} checked in gatepass {target}',
        'gatepass.checked_out'    => '{actor} checked out gatepass {target}',
        'gatepass.returned'       => '{actor} returned gatepass {target} to the gate',

        // Badges
        'visit.badge_issued'     => '{actor} issued badge {target}',
        'visit.badge_returned'   => '{actor} returned badge {target}',

        // Approvals (engine + governance)
        'approval.delegate_created'  => '{actor} set approvals to be covered by {target}',
        'approval.delegate_removed'  => '{actor} cancelled an out-of-office delegation',
        'approval.delegated'         => 'Approval routed to a delegate for {target}',
        'approval.escalated_reminder'=> 'Aging reminder sent for {target}',
        'approval.escalated_admin'   => 'Stalled approval flagged to administrators',

        //Department
        'department.created'      => '{actor} created department {target}',
        'department.updated'      => '{actor} updated department {target}',
        'department.deleted'      => '{actor} deleted department {target}',
        


        // Tenant / settings
        'tenant.settings_updated' => '{actor} updated tenant settings',
        'tenant.plan_changed'     => '{actor} changed subscription plan',

        // System (no human actor)
        'system.backup_completed' => 'Scheduled backup completed',
        'system.cleanup_run'      => 'Scheduled cleanup job ran',
    ];

    public function __construct(
        private AuditLogRepository $repo
    ) {}

    /**
     * Record an audit entry for a named actor.
     *
     * @param int         $tenantId
     * @param int|null    $userId
     * @param string      $actorName  Full name used in the human-readable message
     * @param string      $action     Machine-readable action key (see MESSAGE_TEMPLATES)
     * @param string|null $entityType
     * @param int|null    $entityId
     * @param array|null  $metadata   Pass ['target_label' => 'Jane Doe'] to fill {target}
     * @param string|null $ipAddress
     */
    public function log(
        int     $tenantId,
        ?int    $userId,
        string  $actorName,
        string  $action,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null,
        ?string $ipAddress  = null
    ): void {
        try {
            $message = $this->buildMessage($action, $actorName, $metadata);

            $this->repo->log(
                $tenantId,
                $userId,
                $action,
                $message,
                $entityType,
                $entityId,
                $metadata,
                $ipAddress
            );
        } catch (Throwable $e) {
            // NEVER let audit failures break the caller's business logic.
            error_log('[AuditService] Failed to write audit log: ' . $e->getMessage());
        }
    }

    /**
     * Paginated audit trail for a tenant (filters + Pager-style meta).
     *
     * @return array{data: array, meta: array}
     */
    public function listLogs(int $tenantId, array $params = []): array
    {
        $page    = max(1, (int) ($params['page'] ?? 1));
        $perPage = (int) ($params['per_page'] ?? 25);
        $perPage = max(1, min($perPage, 100));

        return $this->repo->paginate($tenantId, $this->extractFilters($params), $page, $perPage);
    }

    /**
     * Filter-bar dropdowns: actions, entity types and actors.
     *
     * @return array{actions: array, entity_types: array, users: array}
     */
    public function filterOptions(int $tenantId): array
    {
        return [
            'actions'      => $this->repo->actionOptions($tenantId),
            'entity_types' => $this->repo->entityTypeOptions($tenantId),
            'users'        => $this->repo->userOptions($tenantId),
        ];
    }

    /**
     * Normalise the incoming query bag into the filter keys the repository
     * understands. Unknown keys are dropped, never interpolated.
     */
    public function extractFilters(array $params): array
    {
        $allowed = [
            'action', 'entity_type', 'entity_id', 'user_id',
            'date_from', 'date_to', 'search', 'q', 'sort_by', 'sort_dir',
        ];

        $filters = [];

        foreach ($allowed as $key) {
            if (isset($params[$key]) && $params[$key] !== '') {
                $filters[$key] = is_array($params[$key]) ? '' : (string) $params[$key];
            }
        }

        return $filters;
    }

    /**
     * One entry, with its stored metadata decoded into readable blocks:
     *
     *   changes     key => ['before' => mixed, 'after' => mixed, 'changed' => bool]
     *   context     ['method' => …, 'url' => …] recorded by App\Core\Audit
     *   attributes  every other top-level metadata value
     *   related     neighbouring entries for the same entity
     *
     * Returns null when the id does not belong to this tenant.
     */
    public function detail(int $tenantId, int $id): ?array
    {
        $entry = $this->repo->findById($tenantId, $id);

        if ($entry === null) {
            return null;
        }

        $metadata = $this->decodeMetadata($entry['metadata'] ?? null);

        $entry['anonymised'] = (bool) ($metadata['anonymised'] ?? false);
        $entry['changes']    = $this->buildChanges($metadata);
        $entry['context']    = isset($metadata['_request']) && is_array($metadata['_request'])
            ? $metadata['_request']
            : [];
        $entry['attributes'] = $this->remainingAttributes($metadata);
        $entry['action_label'] = $this->describeAction($entry['action']);
        $entry['related']    = $this->repo->relatedTo(
            $tenantId,
            $entry['entity_type'] ?? null,
            isset($entry['entity_id']) ? (int) $entry['entity_id'] : null,
            (int) $entry['id']
        );

        return $entry;
    }

    /**
     * Human label for a machine action code, from the message templates.
     */
    public function describeAction(string $action): string
    {
        $template = self::MESSAGE_TEMPLATES[$action] ?? null;

        if ($template === null) {
            $part = str_contains($action, '.')
                ? substr($action, strpos($action, '.') + 1)
                : $action;

            return ucwords(str_replace('_', ' ', $part));
        }

        $phrase = trim(preg_replace(
            '/\s+/',
            ' ',
            str_replace(['{actor}', '{target}'], ['', ''], $template)
        ));

        // "created gatepass " → "Created gatepass"
        $phrase = rtrim($phrase, ' .,-');
        $phrase = preg_replace('/\s+(for|to|of|on)$/i', '', $phrase);

        return $phrase === '' ? $action : ucfirst($phrase);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Decode the JSON metadata column defensively: an anonymised or malformed
     * payload must never blow up the detail screen.
     */
    private function decodeMetadata(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['value' => $raw];
    }

    /**
     * before/after pairs (written by the update services) flattened into
     * one comparable row set.
     */
    private function buildChanges(array $metadata): array
    {
        $before = isset($metadata['before']) && is_array($metadata['before']) ? $metadata['before'] : [];
        $after  = isset($metadata['after'])  && is_array($metadata['after'])  ? $metadata['after']  : [];

        if ($before === [] && $after === []) {
            return [];
        }

        $keys    = array_unique(array_merge(array_keys($before), array_keys($after)));
        $changes = [];

        foreach ($keys as $key) {
            $oldValue = $before[$key] ?? null;
            $newValue = $after[$key] ?? null;

            $changes[$key] = [
                'before'  => $oldValue,
                'after'   => $newValue,
                'changed' => $this->normalise($oldValue) !== $this->normalise($newValue),
            ];
        }

        return $changes;
    }

    /**
     * Everything that is not a before/after pair or request context.
     */
    private function remainingAttributes(array $metadata): array
    {
        unset($metadata['before'], $metadata['after'], $metadata['_request'], $metadata['target_label']);

        return $metadata;
    }

    /**
     * Compare scalars, booleans and nested arrays on a stable signature.
     */
    private function normalise(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        return trim((string) $value);
    }

    /*
    |--------------------------------------------------------------------------
    | MESSAGE BUILDING
    |--------------------------------------------------------------------------
    */
    private function buildMessage(string $action, string $actorName, ?array $metadata): string
    {
        $template = self::MESSAGE_TEMPLATES[$action] ?? '{actor} ' . $action;

        $target = $metadata['target_label'] ?? '';

        return str_replace(
            ['{actor}', '{target}'],
            [$actorName, $target],
            $template
        );
    }
}