<?php

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-building"></i> Departments
    </h1>
    <div class="page-actions">
        <a href="/settings" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Settings
        </a>
    </div>
</div>

<!-- ===================== CREATE FORM ===================== -->
<div class="table-card mb-4">
    <div class="table-card-head">
        <span class="table-card-title">
            <i class="fa-solid fa-plus"></i> Add Department
        </span>
    </div>

    <div class="card-body" style="max-width:560px">
        <form method="POST" action="/settings/departments/create">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

            <div class="form-group">
                <label class="form-label" for="dept-name">Department Name</label>
                <input type="text"
                       id="dept-name"
                       name="name"
                       class="form-control"
                       placeholder="e.g. Human Resources"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label" for="dept-code">Department Code</label>
                <input type="text"
                       id="dept-code"
                       name="code"
                       class="form-control"
                       placeholder="e.g. HR"
                       required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Department
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== LIST ===================== -->
<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($departments),
        'noun'  => 'department',
    ]); ?>

    <?php if (empty($departments)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-building"></i>
            <div class="empty-state-title">No departments yet</div>
            <div class="empty-state-text">
                Add your first department using the form above.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th width="90">Active</th>
                        <th>Created</th>
                        <th class="text-end" width="110">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td><span class="cell-main"><?= htmlspecialchars($dept->name) ?></span></td>
                            <td><span class="cell-mono"><?= htmlspecialchars($dept->code) ?></span></td>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           <?= $dept->isActive ? 'checked' : '' ?>
                                           onchange="toggleDepartment(<?= (int) $dept->id ?>)">
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td class="cell-date"><?= format_date($dept->createdAt ?? null) ?></td>
                            <td class="table-actions justify-content-end">
                                <button
                                    class="btn btn-sm btn-ghost btn-icon"
                                    title="Edit"
                                    data-edit
                                    data-id="<?= (int) $dept->id ?>"
                                    data-name="<?= htmlspecialchars($dept->name, ENT_QUOTES) ?>"
                                    data-code="<?= htmlspecialchars($dept->code, ENT_QUOTES) ?>"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button
                                    class="btn btn-sm btn-ghost btn-icon"
                                    title="Delete"
                                    onclick="deleteDepartment(<?= (int) $dept->id ?>)"
                                >
                                    <i class="fa-solid fa-trash" style="color:var(--color-danger)"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ===================== EDIT MODAL (simple inline JS version) ===================== -->
<div id="editModal" style="display:none;" class="table-card mt-4">
    <div class="table-card-head">
        <span class="table-card-title">
            <i class="fa-solid fa-pen"></i> Edit Department
        </span>
    </div>

    <div class="card-body" style="max-width:560px">
        <form method="POST" action="/settings/departments/update">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label" for="edit_name">Department Name</label>
                <input type="text" id="edit_name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="edit_code">Department Code</label>
                <input type="text" id="edit_code" name="code" class="form-control" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== SIMPLE JS ACTIONS ===================== -->
<script>
function openEditModal(id, name, code) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_code').value = code;
    const modal = document.getElementById('editModal');
    modal.style.display = 'block';
    modal.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

document.querySelectorAll('[data-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        openEditModal(btn.dataset.id, btn.dataset.name, btn.dataset.code);
    });
});

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function toggleDepartment(id) {
    fetch('/settings/departments/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= \App\Middleware\CSRFMiddleware::token() ?>'
        },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Toggle failed');
        }
    });
}

function deleteDepartment(id) {
    if (!confirm('Are you sure you want to delete this department?')) return;

    fetch('/settings/departments/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': '<?= \App\Middleware\CSRFMiddleware::token() ?>'},
        body: JSON.stringify({id})
    }).then(() => location.reload());
}
</script>
