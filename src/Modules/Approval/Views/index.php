<?php
/** @var array $approvals */

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-stamp"></i> <?= htmlspecialchars($title ?? 'My Approvals') ?>
    </h1>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($approvals),
        'noun'  => 'approval request',
    ]); ?>

    <?php if (empty($approvals)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-circle-check"></i>
            <div class="empty-state-title">All caught up</div>
            <div class="empty-state-text">
                No gatepasses are waiting on your approval right now.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gatepass</th>
                        <th>Type</th>
                        <th>Step</th>
                        <th>Requested By</th>
                        <th>Waiting Since</th>
                        <th class="text-end" width="90">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approvals as $approval): ?>
                        <tr>
                            <td>
                                <a class="cell-main" href="/approvals/<?= (int) $approval['id'] ?>">
                                    <?= htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id'])) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($approval['type_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($approval['step_name']) ?></td>
                            <td><?= htmlspecialchars($approval['requested_by_name']) ?></td>
                            <td class="cell-date"><?= format_date($approval['created_at'] ?? null) ?></td>
                            <td class="table-actions justify-content-end">
                                <a href="/approvals/<?= (int) $approval['id'] ?>" class="btn btn-sm btn-primary">
                                    Review
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
