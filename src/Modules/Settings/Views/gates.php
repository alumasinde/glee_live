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
    <div class="gp-alert gp-alert--danger" style="max-width: 900px;">
        <?= htmlspecialchars((string) $error) ?>
    </div>
<?php endif; ?>

<?php if ($createdKey): ?>
    <div class="table-card mb-4" style="max-width: 900px;">
        <div class="table-card-head">
            <span class="table-card-title">
                <i class="fa-solid fa-key"></i> Device key for gate <?= htmlspecialchars($createdKey[0]) ?>
            </span>
        </div>
        <div class="card-body">
            <p class="form-text" style="margin-top:0; margin-bottom:var(--space-3)">
                Copy this key now — it is shown only once and cannot be recovered later.
            </p>
            <code id="gate-device-key" class="cell-mono" style="display:block; padding:10px; word-break:break-all; background:var(--color-surface-subtle); border:1px solid var(--color-border); border-radius:var(--radius-sm);">
                <?= htmlspecialchars($createdKey[1]) ?>
            </code>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="copyGateKey()">
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

    <div class="card-body" style="max-width:560px">
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
                                            data-gate-edit
                                            data-id="<?= $gid ?>"
                                            data-name="<?= htmlspecialchars($gate['name'], ENT_QUOTES) ?>"
                                            data-code="<?= htmlspecialchars($gate['code'], ENT_QUOTES) ?>"
                                            data-active="<?= (int) $gate['is_active'] ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                <?php endif; ?>

                                <?php if (can('gates.rotate')): ?>
                                    <form method="POST" action="/settings/gates/rotate" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <input type="hidden" name="id" value="<?= $gid ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost btn-icon" title="Rotate device key"
                                                <?= $gate['has_key'] ? '' : 'disabled' ?>
                                                onclick="return confirm('Rotate key? The old device key stops working immediately.');">
                                            <i class="fa-solid fa-rotate"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (can('gates.delete')): ?>
                                    <form method="POST" action="/settings/gates/delete" class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <input type="hidden" name="id" value="<?= $gid ?>">
                                        <button type="submit" class="btn btn-sm btn-ghost btn-icon" title="Delete"
                                                onclick="return confirm('Delete this gate? Gates with scan history must be deactivated instead.');">
                                            <i class="fa-solid fa-trash" style="color:var(--color-danger)"></i>
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
<div id="gateEditModal" style="display:none;" class="table-card mt-4" >
    <div class="table-card-head">
        <span class="table-card-title">
            <i class="fa-solid fa-pen"></i> Edit Gate
        </span>
    </div>

    <div class="card-body" style="max-width:560px">
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
                <button type="button" class="btn btn-secondary" onclick="closeGateEdit()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openGateEdit(id, name, code, active) {
    document.getElementById('gate_edit_id').value = id;
    document.getElementById('gate_edit_name').value = name;
    document.getElementById('gate_edit_code').value = code;
    document.getElementById('gate_edit_active').checked = !!active;
    const modal = document.getElementById('gateEditModal');
    modal.style.display = 'block';
    modal.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

document.querySelectorAll('[data-gate-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openGateEdit(btn.dataset.id, btn.dataset.name, btn.dataset.code, btn.dataset.active === '1');
    });
});

function closeGateEdit() {
    document.getElementById('gateEditModal').style.display = 'none';
}

function copyGateKey() {
    var el = document.getElementById('gate-device-key');
    navigator.clipboard.writeText(el.textContent.trim()).catch(function () {});
}
</script>
