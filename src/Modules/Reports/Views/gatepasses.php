<?php
/** @var array $data  Repository list() result: {data, meta} */

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-id-card-clip"></i> <?= htmlspecialchars($title ?? 'Gatepass Report') ?>
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
        'noun'      => 'gatepass',
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

                        <?php sortLink('g.gatepass_number', 'Number'); ?>
                        <?php sortLink('g.created_at', 'Created'); ?>
                        <?php sortLink('gs.name', 'Status'); ?>
                        <?php sortLink('u.first_name', 'Created By'); ?>
                        <th>Purpose</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $row): ?>
                        <tr>
                            <td class="cell-mono"><?= htmlspecialchars($row['gatepass_number']) ?></td>
                            <td class="cell-date"><?= format_date($row['created_at'] ?? null) ?></td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars(strtolower(str_replace(' ', '_', $row['status_name'] ?? ''))) ?>">
                                    <?= htmlspecialchars($row['status_name'] ?? '—') ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($row['purpose'] ?? '—') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="table-card-foot">
            <?php include base_path('resources/views/partials/pagination.php'); ?>
        </div>
    <?php endif; ?>
</div>
