<?php
/** @var array $approval */

$humanize = fn(string $value): string => ucwords(str_replace('_', ' ', strtolower($value)));
$isActionable = $approval['approval_status'] === 'pending' && $approval['workflow_status'] === 'in_progress';
?>

<div class="approval-card">
    <div class="approval-header">
        <h2>
            <?= htmlspecialchars($approval['gatepass_number'] ?? ('Gatepass #' . $approval['gatepass_id'])) ?>
        </h2>
        <span class="status-badge status-<?= htmlspecialchars(strtolower((string) $approval['workflow_status'])) ?>">
            <?= htmlspecialchars($humanize((string) $approval['workflow_status'])) ?>
        </span>
    </div>

    <div class="approval-body">

        <div class="info-grid">
            <div class="info-item">
                <label>Gatepass</label>
                <span>
                    <a href="/gatepasses/<?= (int) $approval['gatepass_id'] ?>">
                        <?= htmlspecialchars($approval['gatepass_number'] ?? ('#' . $approval['gatepass_id'])) ?>
                    </a>
                </span>
            </div>

            <div class="info-item">
                <label>Type</label>
                <span><?= htmlspecialchars($approval['type_name'] ?? '—') ?></span>
            </div>

            <div class="info-item">
                <label>Status</label>
                <span><?= htmlspecialchars($approval['gatepass_status'] ?? '—') ?></span>
            </div>

            <div class="info-item">
                <label>Current Step</label>
                <span><?= htmlspecialchars($approval['step_name']) ?></span>
            </div>

            <div class="info-item">
                <label>Requested By</label>
                <span>
                    <?= htmlspecialchars($approval['requested_by_name'] ?? '—') ?>
                    <?php if (!empty($approval['department_name'])): ?>
                        <small>(<?= htmlspecialchars($approval['department_name']) ?>)</small>
                    <?php endif; ?>
                </span>
            </div>

            <div class="info-item">
                <label>Submitted</label>
                <span><?= format_date($approval['created_at'] ?? null) ?></span>
            </div>

            <?php if (!empty($approval['is_returnable'])): ?>
                <div class="info-item">
                    <label>Return Expected</label>
                    <span><?= format_date($approval['expected_return_date'] ?? null) ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="purpose-section">
            <label>Purpose</label>
            <div class="purpose-box">
                <?= nl2br(htmlspecialchars((string) ($approval['purpose'] ?? ''))) ?>
            </div>
        </div>

        <?php if (!empty($approval['items'])): ?>
            <div class="section-divider"></div>
            <div class="section-header">
                <h6>Items (<?= count($approval['items']) ?>)</h6>
            </div>
            <div class="table-card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Serial No.</th>
                            <th width="80">Qty</th>
                            <th width="90">Returnable</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approval['items'] as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['item_name']) ?></strong>
                                    <?php if (!empty($item['description'])): ?>
                                        <br><small><?= htmlspecialchars($item['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($item['serial_number'] ?? '—') ?></code></td>
                                <td><?= (int) $item['quantity'] ?></td>
                                <td>
                                    <?= !empty($item['is_returnable'])
                                        ? '<span class="badge badge-info">Yes</span>'
                                        : '<span class="badge badge-secondary">No</span>' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($approval['trail'])): ?>
            <div class="section-divider"></div>
            <div class="section-header">
                <h6>Approval Trail</h6>
            </div>
            <div class="table-card">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="60">Step</th>
                            <th>Name</th>
                            <th>Approver</th>
                            <th>Decision</th>
                            <th>Comment</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approval['trail'] as $entry): ?>
                            <tr>
                                <td><?= (int) $entry['step_order'] ?></td>
                                <td><?= htmlspecialchars($entry['step_name']) ?></td>
                                <td><?= htmlspecialchars($entry['approver_name']) ?></td>
                                <td>
                                    <?php
                                    $badge = match ($entry['status']) {
                                        'approved' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        default    => 'badge-pending',
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>">
                                        <?= $entry['status'] === 'pending' ? 'Awaiting decision' : htmlspecialchars($humanize($entry['status'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string) ($entry['comments'] ?? '')) ?: '—' ?></td>
                                <td class="cell-date"><?= $entry['acted_at'] ? format_date($entry['acted_at']) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div class="action-section">
            <?php if ($isActionable): ?>
                <a href="/approvals/<?= (int) $approval['id'] ?>/approve" class="btn btn-success">
                    Approve
                </a>

                <a href="/approvals/<?= (int) $approval['id'] ?>/reject" class="btn btn-danger">
                    Reject
                </a>

                <?php if (can('approval.reassign')): ?>
                    <a href="/approvals/<?= (int) $approval['id'] ?>/reassign" class="btn btn-secondary">
                        Reassign
                    </a>
                <?php endif; ?>
            <?php elseif ($approval['approval_status'] !== 'pending'): ?>
                <div class="processed-alert">
                    You have already <?= htmlspecialchars($humanize((string) $approval['approval_status'])) ?> this request.
                </div>
            <?php else: ?>
                <div class="processed-alert">
                    This workflow is no longer active.
                </div>
            <?php endif; ?>

            <a href="/approvals" class="btn btn-secondary">
                ← Back to approvals
            </a>
        </div>

    </div>
</div>
