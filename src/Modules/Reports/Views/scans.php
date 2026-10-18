<?php
/** @var array $data  Repository list() result: {data, meta} */

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-satellite-dish"></i> <?= htmlspecialchars($title ?? 'Scan Activity') ?>
    </h1>
    <div class="page-actions">
        <a href="/reports" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
    </div>
</div>

<?php include __DIR__ . '/partials/filters.php'; ?>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total'     => (int) ($data['meta']['total'] ?? count($data['data'] ?? [])),
        'noun'      => 'scan',
        'query'     => trim((string) ($_GET['search'] ?? '')),
        'clearUrl'  => strtok($_SERVER['REQUEST_URI'] ?? '/', '?'),
    ]); ?>

    <?php if (empty($data['data'])): ?>
        <?php include __DIR__ . '/partials/empty.php'; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php include __DIR__ . '/partials/table-header.php'; ?>

                        <?php sortLink('sl.created_at', 'Time'); ?>
                        <?php sortLink('g.name', 'Gate'); ?>
                        <th>Subject</th>
                        <?php sortLink('sl.action', 'Action'); ?>
                        <?php sortLink('sl.source', 'Source'); ?>
                        <th>Operator</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $row): ?>
                        <tr>
                            <td class="cell-date"><?= format_date($row['created_at'] ?? null) ?></td>
                            <td><?= htmlspecialchars($row['gate_name'] ?? 'Unassigned') ?></td>
                            <td>
                                <span class="cell-main">
                                    <?= $row['subject_type'] === 'visit'
                                        ? htmlspecialchars($row['visitor_name'] ?: ('Visit #' . (int) $row['subject_id']))
                                        : htmlspecialchars($row['gatepass_number'] ?: ('Gatepass #' . (int) $row['subject_id'])) ?>
                                </span>
                                <span class="cell-sub"><?= htmlspecialchars(str_replace('_', ' ', (string) $row['subject_type'])) ?></span>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($row['action']) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', (string) $row['action'])) ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($row['source']) ?></td>
                            <td><?= htmlspecialchars(trim((string) $row['operator_name']) ?: '—') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($row['detail'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card-foot">
            <?php include __DIR__ . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>
