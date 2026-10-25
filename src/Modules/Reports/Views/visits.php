<?php
/** @var array $data  Repository list() result: {data, meta} */

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-right-to-bracket"></i> <?= htmlspecialchars($title ?? 'Visit Report') ?>
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
        'noun'     => 'visit',
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
                        <?php sortLink('visitor_name', 'Visitor'); ?>
                        <?php sortLink('host_name', 'Host'); ?>
                        <?php sortLink('department_name', 'Department'); ?>
                        <?php sortLink('status_name', 'Status'); ?>
                        <?php sortLink('visit_type_name', 'Type'); ?>
                        <?php sortLink('vs.checkin_time', 'Check In'); ?>
                        <?php sortLink('vs.created_at', 'Created'); ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $row): ?>
                        <tr>
                            <td><span class="cell-main"><?= htmlspecialchars($row['visitor_name'] ?? '—') ?></span></td>
                            <td><?= htmlspecialchars($row['host_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($row['department_name'] ?? '—') ?></td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars(strtolower(str_replace(' ', '_', $row['status_name'] ?? ''))) ?>">
                                    <?= htmlspecialchars($row['status_name'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($row['visit_type_name'] ?? '—') ?></td>
                            <td class="cell-date"><?= format_date($row['checkin_time'] ?? null) ?></td>
                            <td class="cell-date"><?= format_date($row['created_at'] ?? null) ?></td>
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
