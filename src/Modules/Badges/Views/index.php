<?php

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-id-badge"></i> Badge Operations
    </h1>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($visits),
        'noun'  => 'visit',
    ]); ?>

    <?php if (empty($visits)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-id-badge"></i>
            <div class="empty-state-title">No visits to manage</div>
            <div class="empty-state-text">
                Badge issuing and returns happen against checked-in visits.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Visitor</th>
                        <th>Company</th>
                        <th>Purpose</th>
                        <th>Check-In</th>
                        <th>Check-Out</th>
                        <th>Badge</th>
                        <th class="text-end" width="300">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                        <?php $id = (int) $visit['id']; ?>
                        <tr>
                            <td><span class="cell-main"><?= htmlspecialchars($visit['visitor_name']) ?></span></td>

                            <td><?= htmlspecialchars($visit['company_name'] ?? '—') ?></td>

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
                                    <?php else: ?>
                                        <span class="badge badge-warning">
                                            <?= htmlspecialchars($visit['badge_code']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No badge</span>
                                <?php endif; ?>
                            </td>

                            <td class="table-actions justify-content-end">
                                <?php if (empty($visit['checkin_time']) && empty($visit['checkout_time'])): ?>
                                    <form method="POST" action="/visits/<?= $id ?>/checkin" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-soft-success">Check In</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($visit['checkin_time']) && empty($visit['checkout_time']) && empty($visit['badge_code'])): ?>
                                    <form method="POST" action="/badges/<?= $id ?>/issue" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-secondary">Issue Badge</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($visit['badge_code']) && empty($visit['badge_returned_at'])): ?>
                                    <form method="POST" action="/badges/<?= $id ?>/return" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-secondary">Return Badge</button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!empty($visit['checkin_time']) && empty($visit['checkout_time'])): ?>
                                    <form method="POST" action="/visits/<?= $id ?>/checkout" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-soft-danger"
                                            <?= !empty($visit['badge_code']) && empty($visit['badge_returned_at']) ? 'disabled title="Return badge first"' : '' ?>>
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
