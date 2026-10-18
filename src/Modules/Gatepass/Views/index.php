<?php

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-id-card"></i> Gatepasses
    </h1>

    <div class="page-actions">
        <?php if (can('gatepass.scan')): ?>
            <a href="/gatepasses/scan" class="btn btn-secondary">
                <i class="fa-solid fa-qrcode"></i> Scan
            </a>
        <?php endif; ?>

        <?php if (can('gatepass.create')): ?>
            <a href="/gatepasses/create" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Create Gatepass
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="list-toolbar">
    <?php View::render('components/global-search', [
        'action'      => '/gatepasses',
        'placeholder' => 'Search number, purpose or item…',
    ]); ?>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($gatepasses),
        'noun'  => 'gatepass',
    ]); ?>

    <?php if (empty($gatepasses)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-id-card"></i>
            <div class="empty-state-title">
                <?= trim((string) ($_GET['q'] ?? '')) !== '' ? 'No matching gatepasses' : 'No gatepasses yet' ?>
            </div>
            <div class="empty-state-text">
                <?= trim((string) ($_GET['q'] ?? '')) !== ''
                    ? 'Try a different number, purpose or item name.'
                    : 'Gatepasses you create will appear here.' ?>
            </div>
            <?php if (can('gatepass.create')): ?>
                <a href="/gatepasses/create" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Create Gatepass
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gatepass</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Flags</th>
                        <th>Created</th>
                        <th class="text-end" width="170">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($gatepasses as $g): ?>
                    <?php $id = (int) $g['id']; ?>
                    <tr>
                        <td>
                            <a class="cell-main" href="/gatepasses/<?= $id ?>">
                                <?= htmlspecialchars($g['gatepass_number']) ?>
                            </a>
                            <?php if (!empty($g['purpose'])): ?>
                                <span class="cell-sub"><?= htmlspecialchars($g['purpose']) ?></span>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($g['gatepass_type_name'] ?? '—') ?></td>

                        <td>
                            <span class="badge badge-<?= htmlspecialchars(strtolower(str_replace(' ', '_', $g['status_name'] ?? ''))) ?>">
                                <?= htmlspecialchars($g['status_name'] ?? 'Unknown') ?>
                            </span>
                        </td>

                        <td>
                            <?php if (!empty($g['is_returnable'])): ?>
                                <span class="badge badge-info" title="Returnable item">
                                    <i class="fa-solid fa-rotate-left" style="font-size:0.65rem"></i>&nbsp;Returnable
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($g['needs_approval'])): ?>
                                <span class="badge badge-warning" title="Needs approval">
                                    <i class="fa-solid fa-user-check" style="font-size:0.65rem"></i>&nbsp;Approval
                                </span>
                            <?php endif; ?>
                            <?php if (empty($g['is_returnable']) && empty($g['needs_approval'])): ?>
                                <span class="text-subtle" style="color:var(--color-text-subtle)">—</span>
                            <?php endif; ?>
                        </td>

                        <td class="cell-date"><?= format_date($g['created_at'] ?? null) ?></td>

                        <td class="table-actions justify-content-end">
                            <a href="/gatepasses/<?= $id ?>" class="btn btn-sm btn-ghost btn-icon" title="View">
                                <i class="fa-solid fa-eye"></i>
                            </a>

                            <?php if (can('gatepass.update')): ?>
                                <a href="/gatepasses/<?= $id ?>/edit" class="btn btn-sm btn-ghost btn-icon" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            <?php endif; ?>

                            <?php if (!empty($g['can_checkin']) && can('gatepass.checkin')): ?>
                                <form method="POST" action="/gatepasses/<?= $id ?>/checkin" class="table-action-button">
                                    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                    <button type="submit" class="btn btn-sm btn-soft-success" title="Check in">
                                        In
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if (!empty($g['can_checkout']) && can('gatepass.checkout')): ?>
                                <form method="POST" action="/gatepasses/<?= $id ?>/checkout" class="table-action-button">
                                    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                    <button type="submit" class="btn btn-sm btn-secondary" title="Check out">
                                        Out
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
