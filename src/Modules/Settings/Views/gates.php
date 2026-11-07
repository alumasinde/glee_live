<?php
    $createdKey = is_array($created ?? null) && count($created) === 2 && $created[1] !== ''
        ? $created
        : null;
    $error = $_GET['error'] ?? null;

    use App\Core\View;
?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-door-open"></i> Gates
    </h1>
    <div class="page-actions">
        <a href="/settings" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Settings
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="gp-alert gp-alert--danger u-maxw-900">
        <?= htmlspecialchars((string) $error) ?>
    </div>
<?php endif; ?>

<?php if ($createdKey): ?>
    <div class="table-card mb-4 u-maxw-900">
        <div class="table-card-head">
            <span class="table-card-title">
                <i class="fa-solid fa-key"></i> Device key for gate <?= htmlspecialchars($createdKey[0]) ?>
            </span>
        </div>
        <div class="card-body">
            <p class="form-text u-mt-0 u-mb-s3">
                Copy this key now — it is shown only once and cannot be recovered later.
            </p>
            <code id="gate-device-key" class="cell-mono d-block u-p-10 u-wb-break-all u-bg-subtle u-b-border u-radius-sm">
                <?= htmlspecialchars($createdKey[1]) ?>
            </code>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" data-copy="#gate-device-key">
                    <i class="fa-solid fa-copy"></i> Copy key
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (can('gates.create')): ?>
<!-- ===================== CREATE FORM ===================== -->
<div class="table-card mb-4">
    <div class="table-card-head">
        <span class="table-card-title">
            <i class="fa-solid fa-plus"></i> Add Gate
        </span>
    </div>

    <div class="card-body u-maxw-560">
        <form method="POST" action="/settings/gates">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

            <div class="form-group">
                <label class="form-label" for="gate-name">Gate Name</label>
                <input type="text"
                       id="gate-name"
                       name="name"
                       class="form-control"
                       placeholder="e.g. Main Gate"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label" for="gate-code">Gate Code</label>
                <input type="text"
                       id="gate-code"
                       name="code"
                       class="form-control"
                       placeholder="e.g. MAIN"
                       pattern="[A-Za-z0-9_-]{2,20}"
                       required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Gate
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ===================== LIST ===================== -->
<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($gates),
        'noun'  => 'gate',
    ]); ?>

    <?php if (empty($gates)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-door-open"></i>
            <div class="empty-state-title">No gates yet</div>
            <div class="empty-state-text">
                Create a gate to enable per-gate scanning, occupancy reports and device keys.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Device Key</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end" width="150">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($gates as $gate): ?>
                        <?php $gid = (int) $gate['id']; ?>
                        <tr>
                            <td><span class="cell-main"><?= htmlspecialchars($gate['name']) ?></span></td>
                            <td><span class="cell-mono"><?= htmlspecialchars($gate['code']) ?></span></td>
                            <td>
                                <?php if ($gate['has_key']): ?>
                                    <span class="badge badge-success"><i class="fa-solid fa-key"></i>&nbsp;Issued</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($gate['is_active'])): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="cell-date"><?= format_date($gate['created_at'] ?? null) ?></td>

                            <td class="table-actions justify-content-end">
                                <?php if (can('gates.update')): ?>
                                    <button class="btn btn-sm btn-ghost btn-icon" title="Edit"
                                            data-open-form="#gateEditModal"
                                            data-field-id="<?= $gid ?>"
                                            data-field-name="<?= htmlspecialchars($gate['name'], ENT_QUOTES) ?>"
                                            data-field-code="<?= htmlspecialchars($gate['code'], ENT_QUOTES) ?>"
                                            data-field-active="<?= (int) $gate['is_active'] ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if (can('gates.rotate')): ?>
                                    <form method="POST" action="/settings/gates/rotate" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <input type="hidden" name="id" value="<?= $gid ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost btn-icon" title="Rotate device key"
                                                <?= $gate['has_key'] ? '' : 'disabled' ?>
                                                data-confirm="Rotate key? The old device key stops working immediately.">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (can('gates.delete')): ?>
                                    <form method="POST" action="/settings/gates/delete" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <input type="hidden" name="id" value="<?= $gid ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost btn-icon" title="Delete"
                                                data-confirm="Delete this gate? Gates with scan history must be deactivated instead.">
                                            <i class="fa-solid fa-trash text-danger"></i>
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

<!-- ===================== EDIT FORM ===================== -->
<div id="gateEditModal" class="table-card mt-4 u-d-none" >
    <div class="table-card-head">
        <span class="table-card-title">
            <i class="fa-solid fa-pen"></i> Edit Gate
        </span>
    </div>

    <div class="card-body u-maxw-560">
        <form method="POST" action="/settings/gates/update">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
            <input type="hidden" name="id" id="gate_edit_id">

            <div class="form-group">
                <label class="form-label" for="gate_edit_name">Gate Name</label>
                <input type="text" name="name" id="gate_edit_name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="gate_edit_code">Gate Code</label>
                <input type="text" name="code" id="gate_edit_code" class="form-control" pattern="[A-Za-z0-9_-]{2,20}" required>
            </div>

            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="is_active" id="gate_edit_active" value="1" class="form-check-input">
                    <span class="form-check-label">Active</span>
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-secondary" data-close="#gateEditModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

