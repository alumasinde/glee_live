<?php
/**
 * Pending approval queue.
 *
 * @var array         $approvals
 * @var array|null    $data       ['meta' => pagination meta]
 * @var string|null   $sort       'age' | 'recent'
 * @var int|null      $reminderHours
 * @var string|null   $error
 */

use App\Core\View;

$meta   = $data['meta'] ?? [];
$total  = $meta['total'] ?? count($approvals);
$sort   = $sort ?? 'age';
$stale  = max(1, (int) ($reminderHours ?? 24));

$sortLink = static function (string $key, string $label) use ($sort): string {
    $query            = $_GET;
    $query['sort']    = $key;
    unset($query['page']);

    $url = '?' . htmlspecialchars(http_build_query($query), ENT_QUOTES);

    return '<a class="' . ($sort === $key ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline') . '" href="' . $url . '">'
        . htmlspecialchars($label) . '</a>';
};
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-stamp"></i> <?= htmlspecialchars($title ?? 'My Approvals') ?>
    </h1>
    <div class="page-actions">
        <?= $sortLink('age', 'Longest waiting') ?>
        <?= $sortLink('recent', 'Most recent') ?>
        <?php if (can('approval.delegate')): ?>
            <a class="btn btn-sm btn-secondary" href="/approvals/delegation">
                <i class="fa-solid fa-people-arrows"></i> Out-of-office cover
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => $total,
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
                        <?php
                        $hours   = (int) ($approval['waiting_hours'] ?? 0);
                        $overdue = $hours >= $stale;
                        ?>
                        <tr class="<?= $overdue ? 'row-warning' : '' ?>">
                            <td>
                                <a class="cell-main" href="/approvals/<?= (int) $approval['id'] ?>">
                                    <?= htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id'])) ?>
                                </a>
                            </td>
                            <td><?= htmlspecialchars($approval['type_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($approval['step_name']) ?></td>
                            <td><?= htmlspecialchars($approval['requested_by_name']) ?></td>
                            <td class="cell-date">
                                <?= format_date($approval['created_at'] ?? null) ?>
                                <?php if ($hours > 0): ?>
                                    <span class="badge <?= $overdue ? 'badge-danger' : 'badge-muted' ?>">
                                        <?= $hours >= 24 ? floor($hours / 24) . 'd' : $hours . 'h' ?> waiting
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($approval['escalation_count'])): ?>
                                    <span class="badge badge-warning" title="Escalation reminders already sent">
                                        nudged <?= (int) $approval['escalation_count'] ?>&times;
                                    </span>
                                <?php endif; ?>
                            </td>
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

        <?php $data = ['meta' => $meta]; ?>
        <?php include base_path('resources/views/partials/pagination.php'); ?>
    <?php endif; ?>
</div>
