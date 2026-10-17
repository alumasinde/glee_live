<?php
    $createdKey = null;
    if (!empty($_GET['created'])) {
        $parts = explode('|', (string) $_GET['created'], 2);
        if (count($parts) === 2 && $parts[1] !== '') {
            $createdKey = $parts;
        }
    }
    $error = $_GET['error'] ?? null;
?>

<h1 class="page-heading">
    <i class="fa-solid fa-door-open"></i> Gates
</h1>

<?php if ($error): ?>
    <div class="gp-alert gp-alert--danger" style="max-width: 900px;">
        <?= htmlspecialchars((string) $error) ?>
    </div>
<?php endif; ?>

<?php if ($createdKey): ?>
    <div class="form-card" style="max-width: 900px;">
        <h3><i class="fa-solid fa-key"></i> Device key for gate <?= htmlspecialchars($createdKey[0]) ?></h3>
        <p>Copy this key now — it is shown only once and cannot be recovered later.</p>
        <code id="gate-device-key" style="display:block; padding:10px; word-break:break-all; background:#f3f4f6; border-radius:6px;">
            <?= htmlspecialchars($createdKey[1]) ?>
        </code>
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="copyGateKey()">
                <i class="fa-solid fa-copy"></i> Copy key
            </button>
        </div>
    </div>
<?php endif; ?>

<!-- ===================== CREATE FORM ===================== -->
<div class="form-card">

    <form method="POST" action="/settings/gates">
        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

        <div class="form-group">
            <label class="form-label">Gate Name</label>
            <input type="text"
                   name="name"
                   class="form-control"
                   placeholder="e.g. Main Gate"
                   required>

            <label class="form-label">Gate Code</label>
            <input type="text"
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

<!-- ===================== LIST ===================== -->
<div class="table-card">

    <h3 style="margin-bottom: 15px;">Gate List</h3>

    <?php if (!empty($gates)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Device Key</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="width: 300px;">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($gates as $gate): ?>
                    <tr>
                        <td><?= htmlspecialchars($gate['name']) ?></td>
                        <td><?= htmlspecialchars($gate['code']) ?></td>
                        <td>
                            <?php if ($gate['has_key']): ?>
                                <span class="badge badge-success"><i class="fa-solid fa-key"></i> issued</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">none</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= $gate['is_active'] ? 'Active' : 'Inactive' ?>
                        </td>
                        <td><?= htmlspecialchars($gate['created_at'] ?? '-') ?></td>

                        <td style="display:flex; gap:6px; flex-wrap:wrap;">
                            <button class="btn btn-sm btn-secondary"
                                    onclick="openGateEdit(<?= (int) $gate['id'] ?>, '<?= htmlspecialchars($gate['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($gate['code'], ENT_QUOTES) ?>', <?= (int) $gate['is_active'] ?>)">
                                Edit
                            </button>

                            <form method="POST" action="/settings/gates/rotate" style="display:inline;">
                                <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                <input type="hidden" name="id" value="<?= (int) $gate['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary"
                                    <?= $gate['has_key'] ? '' : 'disabled' ?>
                                    onclick="return confirm('Rotate key? The old device key stops working immediately.');">
                                    Rotate key
                                </button>
                            </form>

                            <form method="POST" action="/settings/gates/delete" style="display:inline;">
                                <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                <input type="hidden" name="id" value="<?= (int) $gate['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Delete this gate? Gates with scan history must be deactivated instead.');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No gates found. Create one to enable per-gate scanning and occupancy.</p>
    <?php endif; ?>

</div>

<!-- ===================== EDIT FORM ===================== -->
<div id="gateEditModal" style="display:none;" class="form-card">

    <h3>Edit Gate</h3>

    <form method="POST" action="/settings/gates/update">
        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
        <input type="hidden" name="id" id="gate_edit_id">

        <div class="form-group">
            <label class="form-label">Gate Name</label>
            <input type="text" name="name" id="gate_edit_name" class="form-control" required>

            <label class="form-label">Gate Code</label>
            <input type="text" name="code" id="gate_edit_code" class="form-control" pattern="[A-Za-z0-9_-]{2,20}" required>

            <label class="form-label">
                <input type="checkbox" name="is_active" id="gate_edit_active" value="1"> Active
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update</button>
            <button type="button" class="btn btn-secondary" onclick="closeGateEdit()">Cancel</button>
        </div>
    </form>

</div>

<script>
function openGateEdit(id, name, code, active) {
    document.getElementById('gate_edit_id').value = id;
    document.getElementById('gate_edit_name').value = name;
    document.getElementById('gate_edit_code').value = code;
    document.getElementById('gate_edit_active').checked = !!active;
    document.getElementById('gateEditModal').style.display = 'block';
}

function closeGateEdit() {
    document.getElementById('gateEditModal').style.display = 'none';
}

function copyGateKey() {
    var el = document.getElementById('gate-device-key');
    navigator.clipboard.writeText(el.textContent.trim()).catch(function () {});
}
</script>
