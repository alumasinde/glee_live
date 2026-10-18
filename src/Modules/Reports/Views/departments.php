<?php
/** @var array $rows Department summary rows */

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-building"></i> <?= htmlspecialchars($title ?? 'Department Activity') ?>
    </h1>
    <div class="page-actions">
        <?php if (can($export_perm ?? '')): ?>
            <a href="/reports/departments/export" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-file-csv"></i> Export CSV
            </a>
        <?php endif; ?>
        <a href="/reports" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
    </div>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($rows),
        'noun'  => 'department',
    ]); ?>

    <?php if (empty($rows)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-building"></i>
            <div class="empty-state-title">No departments yet</div>
            <div class="empty-state-text">
                Add departments under Settings to see their gatepass and visit activity.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Staff</th>
                        <th>Gatepasses (total)</th>
                        <th>Gatepasses (this month)</th>
                        <th>On site now</th>
                        <th>Visits (total)</th>
                        <th>Visits (this month)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td>
                                <span class="cell-main"><?= htmlspecialchars($row['name']) ?></span>
                                <span class="cell-sub cell-mono"><?= htmlspecialchars($row['code'] ?? '') ?></span>
                            </td>
                            <td><?= (int) $row['staff_count'] ?></td>
                            <td><?= (int) $row['gatepasses_total'] ?></td>
                            <td><?= (int) $row['gatepasses_month'] ?></td>
                            <td>
                                <?php $onSite = (int) $row['gatepasses_on_site']; ?>
                                <span class="badge <?= $onSite > 0 ? 'badge-success' : 'badge-secondary' ?>">
                                    <?= $onSite ?>
                                </span>
                            </td>
                            <td><?= (int) $row['visits_total'] ?></td>
                            <td><?= (int) $row['visits_month'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
