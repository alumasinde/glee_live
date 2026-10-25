<?php
/** @var array $approval */
/** @var array $stepPeers */
/** @var array $candidates */

$number = htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id']));
?>

<div class="card">
    <div class="card-header">
        <h5>Reassign Approval</h5>
    </div>

    <div class="card-body">

        <p>
            Gatepass <strong><?= $number ?></strong>
            (<?= htmlspecialchars($approval['type_name'] ?? 'no type') ?>)
            requested by <strong><?= htmlspecialchars($approval['requested_by_name'] ?? '—') ?></strong>
            is waiting at step <strong><?= htmlspecialchars($approval['step_name'] ?? '—') ?></strong>
            on <strong><?= htmlspecialchars($approval['approver_name'] ?? 'an approver') ?></strong>.
        </p>

        <?php if (!empty($approval['purpose'])): ?>
            <p class="text-muted"><?= nl2br(htmlspecialchars($approval['purpose'])) ?></p>
        <?php endif; ?>

        <?php if ($approval['approval_status'] !== 'pending' || $approval['workflow_status'] !== 'in_progress'): ?>
            <div class="alert alert-warning">
                This approval is no longer pending on an active workflow, so it cannot be reassigned.
            </div>
        <?php else: ?>

            <?php $pendingPeers = array_values(array_filter($stepPeers, fn($p) => $p['status'] === 'pending')); ?>
            <?php if (count($pendingPeers) > 1): ?>
                <div class="alert alert-info">
                    Other approvers are also pending on this step — they can act too. Reassigning this row
                    only moves it to a new person:
                    <?php foreach ($pendingPeers as $peer): ?>
                        <?php if ((int) $peer['id'] !== (int) $approval['id']): ?>
                            <a href="/approvals/<?= (int) $peer['id'] ?>/reassign"><?= htmlspecialchars($peer['approver_name'] ?? 'Approver') ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($candidates)): ?>
                <div class="alert alert-warning">
                    No eligible users found in this tenant (the requester cannot approve their own gatepass).
                </div>
            <?php else: ?>

                <form method="POST" action="/approvals/<?= (int) $approval['id'] ?>/reassign">
                    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

                    <div class="form-group">
                        <label for="approver_user_id">New approver *</label>
                        <select id="approver_user_id" name="approver_user_id" class="form-control" required>
                            <option value="">Select user</option>
                            <?php foreach ($candidates as $c): ?>
                                <option value="<?= (int) $c['id'] ?>">
                                    <?= htmlspecialchars($c['name']) ?>
                                    <?= !empty($c['roles']) ? ' — ' . htmlspecialchars($c['roles']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>The new approver is notified by email as soon as the reassignment is saved.</small>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-people-arrows"></i> Reassign
                        </button>

                        <a href="/approvals/<?= (int) $approval['id'] ?>" class="btn btn-secondary">
                            Cancel
                        </a>
                    </div>
                </form>

            <?php endif; ?>

        <?php endif; ?>

    </div>
</div>
