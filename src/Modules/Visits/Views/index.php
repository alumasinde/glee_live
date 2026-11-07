<?php

use App\Core\View;

$canCheckIn  = can('visits.check_in');
$canCheckOut = can('visits.check_out');
$canIssue    = can('badges.issue');
$canReturn   = can('badges.return');

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-person-walking-arrow-right"></i> Active Visits
    </h1>

    <div class="page-actions">
        <?php if (can('visits.create')): ?>
            <a href="/visits/create" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> New Visit
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($visits),
        'noun'  => 'active visit',
    ]); ?>

    <?php if (empty($visits)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-person-walking-arrow-right"></i>
            <div class="empty-state-title">Nobody on site</div>
            <div class="empty-state-text">
                Checked-in visitors who have not checked out yet appear here.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Visitor</th>
                        <th>Department</th>
                        <th>Purpose</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Badge</th>
                        <th class="text-end" width="290">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                        <?php
                            $id               = (int) $visit['id'];
                            $badgeNotReturned = !empty($visit['badge_code']) && empty($visit['badge_returned_at']);
                        ?>
                        <tr>
                            <td>
                                <span class="cell-main"><?= htmlspecialchars($visit['visitor_name']) ?></span>
                                <span class="cell-sub"><?= htmlspecialchars($visit['visitor_company'] ?? '—') ?></span>
                            </td>

                            <td><?= htmlspecialchars($visit['department_name'] ?? '—') ?></td>

                            <td class="text-muted"><?= htmlspecialchars($visit['purpose'] ?? '—') ?></td>

                            <td class="cell-date">
                                <?= $visit['checkin_time'] ? htmlspecialchars($visit['checkin_time']) : '—' ?>
                            </td>

                            <td class="cell-date">
                                <?= $visit['checkout_time'] ? htmlspecialchars($visit['checkout_time']) : '—' ?>
                            </td>

                            <td>
                                <?php if (!empty($visit['badge_code'])): ?>
                                    <?php if (!empty($visit['badge_returned_at'])): ?>
                                        <span class="badge badge-success">
                                            <?= htmlspecialchars($visit['badge_code']) ?> · Returned
                                        </span>
                                    <?php elseif (!empty($visit['is_active'])): ?>
                                        <span class="badge badge-warning">
                                            <?= htmlspecialchars($visit['badge_code']) ?> · Active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">
                                            <?= htmlspecialchars($visit['badge_code']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No badge</span>
                                <?php endif; ?>
                            </td>

                            <td class="table-actions justify-content-end">
                                <?php if ($canCheckIn && empty($visit['checkin_time']) && empty($visit['checkout_time'])): ?>
                                    <form method="POST" action="/visits/<?= $id ?>/checkin" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-soft-success">Check In</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($canIssue && !empty($visit['checkin_time']) && empty($visit['checkout_time']) && empty($visit['badge_code'])): ?>
                                    <form method="POST" action="/badges/<?= $id ?>/issue" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-secondary">Issue Badge</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($canReturn && $badgeNotReturned): ?>
                                    <form method="POST" action="/badges/<?= $id ?>/return" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-secondary">Return Badge</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($canCheckOut && !empty($visit['checkin_time']) && empty($visit['checkout_time'])): ?>
                                    <form method="POST" action="/visits/<?= $id ?>/checkout" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-soft-danger" <?= $badgeNotReturned ? 'disabled title="Return badge first"' : '' ?>>
                                            Check Out
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
