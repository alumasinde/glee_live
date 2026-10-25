<?php /** @var array $approval */ ?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5>Approve Gatepass</h5>
    </div>

    <div class="card-body">

        <p>
            You are approving gatepass
            <strong><?= htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id'])) ?></strong>
            (<?= htmlspecialchars($approval['type_name'] ?? 'no type') ?>)
            requested by <strong><?= htmlspecialchars($approval['requested_by_name'] ?? '—') ?></strong>
            at step <strong><?= htmlspecialchars($approval['step_name']) ?></strong>.
        </p>

        <?php if (!empty($approval['purpose'])): ?>
            <p class="text-muted">
                <i class="fa-solid fa-bullseye"></i>
                <?= nl2br(htmlspecialchars($approval['purpose'])) ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="/approvals/<?= (int) $approval['approval_id'] ?>/approve">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
            <div class="mb-3">
                <label class="form-label">Comment (Optional)</label>
                <textarea name="comment"
                          class="form-control"
                          rows="3"
                          placeholder="Shown to the requester and in the approval trail"></textarea>
            </div>

            <button type="submit" class="btn btn-success">
                Confirm Approval
            </button>

            <a href="/approvals" class="btn btn-secondary">
                Cancel
            </a>
        </form>

    </div>
</div>
