<?php /** @var array $approvals */ ?>

<div class="card">
    <div class="card-header">
        <h5><?= htmlspecialchars($title ?? 'My Approvals') ?></h5>
    </div>

    <div class="card-body">
        <?php if (empty($approvals)): ?>
            <div class="alert alert-info">
                No pending approvals.
            </div>
        <?php else: ?>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Gatepass</th>
                        <th>Step</th>
                        <th>Status</th>
                        <th>Requested By</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($approvals as $index => $approval): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>#<?= htmlspecialchars($approval['gatepass_id']) ?></td>
                            <td><?= htmlspecialchars($approval['step_name']) ?></td>
                            <td>
                                <span class="badge bg-warning">
                                    <?= htmlspecialchars($approval['approval_status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($approval['requested_by_name']) ?></td>
                            <td><?= htmlspecialchars($approval['created_at']) ?></td>
                            <td>
    <a href="/approvals/<?= $approval['id'] ?>" class="btn btn-sm btn-primary">
        View
    </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>