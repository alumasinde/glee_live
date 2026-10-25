<?php
/**
 * One audit entry, its decoded metadata and the trail around the same entity.
 *
 * @var array $entry
 */

$entry     = $entry ?? [];
$changes   = $entry['changes'] ?? [];
$attrs     = $entry['attributes'] ?? [];
$context   = $entry['context'] ?? [];
$related   = $entry['related'] ?? [];

// IP addresses are PII: enough to spot a shared office or an anomaly, never
// enough to identify a person on a residential connection.
$maskIp = static function (?string $ip): string {
    if ($ip === null || trim($ip) === '') {
        return '—';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);

        return $parts[0] . '.' . $parts[1] . '.*.*';
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);

        return implode(':', array_slice($parts, 0, 2)) . '::…';
    }

    return '—';
};

$value = static function (mixed $v): string {
    if ($v === null || $v === '') {
        return '—';
    }

    if (is_bool($v)) {
        return $v ? 'Yes' : 'No';
    }

    if (is_array($v)) {
        return htmlspecialchars(json_encode($v, JSON_UNESCAPED_SLASHES), ENT_QUOTES);
    }

    return htmlspecialchars((string) $v, ENT_QUOTES);
};
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-clock-rotate-left"></i> <?= htmlspecialchars($title ?? 'Audit Entry') ?>
    </h1>
    <div class="page-actions">
        <a class="btn btn-sm btn-outline" href="/audit">
            <i class="fa-solid fa-arrow-left"></i> Back to trail
        </a>
    </div>
</div>

<?php if (!empty($entry['anonymised'])): ?>
    <div class="gp-alert gp-alert--warning">
        Personal details in this entry were removed by the retention job. The action,
        outcome and entity are still intact.
    </div>
<?php endif; ?>

<div class="table-card" style="max-width:900px;">
    <div class="table-card-head">
        <span class="table-card-title"><?= htmlspecialchars($entry['action_label'] ?? ($entry['action'] ?? '')) ?></span>
        <span class="cell-date"><?= htmlspecialchars($entry['created_at'] ?? '') ?></span>
    </div>
    <div class="card-body">
        <table class="table table-compact">
            <tbody>
                <tr>
                    <th width="160">Summary</th>
                    <td><?= htmlspecialchars($entry['message'] ?? '') ?: '—' ?></td>
                </tr>
                <tr>
                    <th>Action code</th>
                    <td><span class="chip"><?= htmlspecialchars($entry['action'] ?? '') ?></span></td>
                </tr>
                <tr>
                    <th>Actor</th>
                    <td>
                        <?= $value($entry['user_name'] ?? null) ?>
                        <?php if (!empty($entry['user_email'])): ?>
                            <span class="cell-date"><?= htmlspecialchars($entry['user_email']) ?></span>
                        <?php elseif (empty($entry['user_id'])): ?>
                            <span class="cell-date">system / unauthenticated</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Entity</th>
                    <td>
                        <?php if (!empty($entry['entity_type'])): ?>
                            <?= htmlspecialchars(ucfirst((string) $entry['entity_type'])) ?>
                            <?php if (!empty($entry['entity_id'])): ?>
                                <span class="chip">#<?= (int) $entry['entity_id'] ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>Tenant</th>
                    <td><?= $value($entry['tenant_name'] ?? null) ?></td>
                </tr>
                <tr>
                    <th>IP address</th>
                    <td class="cell-date"><?= $maskIp($entry['ip_address'] ?? null) ?></td>
                </tr>
                <?php if (!empty($context['method']) || !empty($context['url'])): ?>
                    <tr>
                        <th>Request</th>
                        <td class="cell-date">
                            <?= $value($context['method'] ?? '') ?> <?= htmlspecialchars((string) ($context['url'] ?? '')) ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($changes)): ?>
    <div class="table-card" style="max-width:900px;margin-top:var(--space-4);">
        <div class="table-card-head">
            <span class="table-card-title">Field changes</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th>Before</th>
                        <th>After</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($changes as $field => $change): ?>
                        <tr class="<?= empty($change['changed']) ? '' : 'row-warning' ?>">
                            <td class="cell-main"><?= htmlspecialchars((string) $field) ?></td>
                            <td><?= $value($change['before']) ?></td>
                            <td><?= $value($change['after']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($attrs)): ?>
    <div class="table-card" style="max-width:900px;margin-top:var(--space-4);">
        <div class="table-card-head">
            <span class="table-card-title">Recorded context</span>
        </div>
        <div class="card-body">
            <table class="table table-compact">
                <tbody>
                    <?php foreach ($attrs as $key => $val): ?>
                        <tr>
                            <th width="160"><?= htmlspecialchars((string) $key) ?></th>
                            <td><?= $value($val) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($related)): ?>
    <div class="table-card" style="max-width:900px;margin-top:var(--space-4);">
        <div class="table-card-head">
            <span class="table-card-title">Same <?= strtolower((string) ($entry['entity_type'] ?? 'entity')) ?> #<?= (int) ($entry['entity_id'] ?? 0) ?></span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="150">When</th>
                        <th>Action</th>
                        <th>Actor</th>
                        <th width="70"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($related as $row): ?>
                        <tr>
                            <td class="cell-date"><?= format_date($row['created_at'] ?? null) ?></td>
                            <td><span class="chip"><?= htmlspecialchars($row['action'] ?? '') ?></span></td>
                            <td><?= htmlspecialchars(trim((string) ($row['user_name'] ?? '')) ?: 'System') ?></td>
                            <td class="table-actions justify-content-end">
                                <a class="btn btn-sm btn-outline" href="/audit/<?= (int) $row['id'] ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
