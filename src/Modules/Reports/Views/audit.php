<?php
/** @var array $data  Repository list() result: {data, meta} */

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-clock-rotate-left"></i> <?= htmlspecialchars($title ?? 'Audit Report') ?>
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
        'total'    => (int) ($data['meta']['total'] ?? count($data['data'] ?? [])),
        'noun'     => 'audit entry',
        'query'    => trim((string) ($_GET['search'] ?? '')),
        'clearUrl' => strtok($_SERVER['REQUEST_URI'] ?? '/', '?'),
    ]); ?>

    <?php if (empty($data['data'])): ?>
        <?php include __DIR__ . '/partials/empty.php'; ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <?php include __DIR__ . '/partials/table-header.php'; ?>
                        <?php sortLink('created_at', 'Date'); ?>
                        <th>Entity</th>
                        <th>Message</th>
                        <th>User</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $row): ?>
                        <tr>
                            <td class="cell-date"><?= format_date($row['created_at'] ?? null) ?></td>
                            <td>
                                <span class="badge badge-secondary"><?= htmlspecialchars($row['entity_type'] ?? '—') ?></span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($row['message'] ?? '') ?></td>
                            <td><?= htmlspecialchars($row['user_name'] ?? '—') ?></td>
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
