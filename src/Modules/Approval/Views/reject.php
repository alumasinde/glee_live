<?php /** @var array $approval */ ?>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5>Reject Gatepass</h5>
    </div>

    <div class="card-body">

        <p>
            You are rejecting gatepass
            <strong><?= htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id'])) ?></strong>
            (<?= htmlspecialchars($approval['type_name'] ?? 'no type') ?>)
            requested by <strong><?= htmlspecialchars($approval['requested_by_name'] ?? '—') ?></strong>.
            The requester will be notified with your reason.
        </p>

        <?php if (!empty($approval['purpose'])): ?>
            <p class="text-muted">
                <i class="fa-solid fa-bullseye"></i>
                <?= nl2br(htmlspecialchars($approval['purpose'])) ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="/approvals/<?= (int) $approval['approval_id'] ?>/reject">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
            <div class="mb-3">
                <label class="form-label">Reason for Rejection *</label>
                <textarea name="reason"
                          class="form-control"
                          rows="3"
                          required></textarea>
            </div>

            <button type="submit" class="btn btn-danger">
                Confirm Rejection
            </button>

            <a href="/approvals" class="btn btn-secondary">
                Cancel
            </a>
        </form>

    </div>
</div>
