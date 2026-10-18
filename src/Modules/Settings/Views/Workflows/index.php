<?php
/** @var array $workflows */

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-diagram-project"></i> <?= htmlspecialchars($title ?? 'Workflows') ?>
    </h1>

    <div class="page-actions">
        <a href="/settings/workflows/create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> New Workflow
        </a>
        <a href="/settings" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($workflows),
        'noun'  => 'workflow',
    ]); ?>

    <?php if (empty($workflows)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-diagram-project"></i>
            <div class="empty-state-title">No workflows configured</div>
            <div class="empty-state-text">
                Workflows define the approval steps a gatepass must pass through.
            </div>
            <a href="/settings/workflows/create" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> New Workflow
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th width="80">Steps</th>
                        <th width="110">Status</th>
                        <th class="text-end" width="200">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($workflows as $workflow): ?>
                        <?php $wid = (int) $workflow['id']; ?>
                        <tr>
                            <td><span class="cell-main"><?= htmlspecialchars($workflow['name']) ?></span></td>

                            <td class="text-muted"><?= htmlspecialchars($workflow['description'] ?? '—') ?></td>

                            <td><span class="badge badge-secondary"><?= (int) $workflow['step_count'] ?></span></td>

                            <td>
                                <?php if ((int) $workflow['is_active'] === 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>

                            <td class="table-actions justify-content-end">
                                <a href="/settings/workflows/<?= $wid ?>/steps" class="btn btn-sm btn-secondary">
                                    Steps
                                </a>
                                <a href="/settings/workflows/<?= $wid ?>/assign" class="btn btn-sm btn-ghost btn-icon" title="Assign to types">
                                    <i class="fa-solid fa-link"></i>
                                </a>
                                <a href="/settings/workflows/<?= $wid ?>/edit" class="btn btn-sm btn-ghost btn-icon" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
