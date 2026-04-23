<?php /** @var array $approval */ ?>

<div class="approval-card">
    <div class="approval-header">
        <h2>Approval Details</h2>
        <span class="status-badge status-<?= strtolower($approval['workflow_status']) ?>">
            <?= htmlspecialchars($approval['workflow_status']) ?>
        </span>
    </div>

    <div class="approval-body">

        <div class="info-grid">
            <div class="info-item">
                <label>Gatepass ID</label>
                <span>#<?= $approval['gatepass_id'] ?></span>
            </div>

            <div class="info-item">
                <label>Current Step</label>
                <span><?= htmlspecialchars($approval['step_name']) ?></span>
            </div>

            <div class="info-item">
                <label>Requested By</label>
                <span><?= htmlspecialchars($approval['requested_by_name']) ?></span>
            </div>

            <div class="info-item">
                <label>Created At</label>
                <span><?= htmlspecialchars($approval['created_at']) ?></span>
            </div>
        </div>

        <div class="purpose-section">
            <label>Purpose</label>
            <div class="purpose-box">
                <?= nl2br(htmlspecialchars($approval['purpose'] ?? '')) ?>
            </div>
        </div>

        <div class="action-section">
            <?php if ($approval['workflow_status'] === 'in_progress'): ?>
                <a href="/approvals/<?= $approval['id'] ?>/approve" class="btn btn-success">
    ✓ Approve
</a>

<a href="/approvals/<?= $approval['id'] ?>/reject" class="btn btn-danger">
    ✕ Reject
</a>
            <?php else: ?>
                <div class="processed-alert">
                    This approval has already been processed.
                </div>
            <?php endif; ?>

            <a href="/approvals" class="btn btn-secondary">
                ← Back
            </a>
        </div>

    </div>
</div>