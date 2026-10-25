<?php
/** @var array $data  Repository list() result: {data, meta} */

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-user-check"></i> <?= htmlspecialchars($title ?? 'Visitor Report') ?>
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
        'noun'     => 'visitor',
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
                        <?php sortLink('first_name', 'Name'); ?>
                        <?php sortLink('created_at', 'Registered'); ?>
                        <th>Phone</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['data'] as $row): ?>
                        <tr>
                            <td>
                                <span class="cell-main"><?= htmlspecialchars($row['first_name'] ?? '') ?></span>
                                <?php if (!empty($row['last_name'])): ?>
                                    <span class="cell-sub"><?= htmlspecialchars($row['last_name']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-date"><?= format_date($row['created_at'] ?? null) ?></td>
                            <td class="cell-mono"><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($row['email'] ?? '—') ?></td>
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
