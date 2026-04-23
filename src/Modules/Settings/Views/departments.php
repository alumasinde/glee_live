<h1 class="page-heading">
    <i class="fa-solid fa-building"></i> Departments
</h1>

<!-- ===================== CREATE FORM ===================== -->
<div class="form-card">

    <form method="POST" action="/settings/departments/create">

        <div class="form-group">
            <label class="form-label">Department Name</label>
            <input type="text"
                   name="name"
                   class="form-control"
                   placeholder="e.g. Human Resources"
                   required>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Create Department
            </button>
        </div>

    </form>

</div>

<!-- ===================== LIST ===================== -->
<div class="form-card">

    <h3 style="margin-bottom: 15px;">Department List</h3>

    <?php if (!empty($departments)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="width: 220px;">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td>
                            <?= htmlspecialchars($dept->name) ?>
                        </td>

                        <td>
                            <?php if ($dept->isActive): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars($dept->createdAt ?? '-') ?>
                        </td>

                        <td style="display:flex; gap:6px; flex-wrap:wrap;">

                            <!-- EDIT (opens inline modal or separate page if you want) -->
                            <button 
                                class="btn btn-sm btn-secondary"
                                onclick="openEditModal(<?= $dept->id ?>, '<?= htmlspecialchars($dept->name) ?>')"
                            >
                                Edit
                            </button>

                            <!-- DEACTIVATE / ACTIVATE -->
                            <button 
                                class="btn btn-sm btn-warning"
                                onclick="toggleDepartment(<?= $dept->id ?>)"
                            >
                                Toggle
                            </button>

                            <!-- DELETE -->
                            <button 
                                class="btn btn-sm btn-danger"
                                onclick="deleteDepartment(<?= $dept->id ?>)"
                            >
                                Delete
                            </button>

                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>

        </table>
    <?php else: ?>
        <p>No departments found.</p>
    <?php endif; ?>

</div>

<!-- ===================== EDIT MODAL (simple inline JS version) ===================== -->
<div id="editModal" style="display:none;" class="form-card">

    <h3>Edit Department</h3>

    <form method="POST" action="/settings/departments/update">

        <input type="hidden" name="id" id="edit_id">

        <div class="form-group">
            <label class="form-label">Department Name</label>
            <input type="text"
                   name="name"
                   id="edit_name"
                   class="form-control"
                   required>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                Update
            </button>

            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                Cancel
            </button>
        </div>

    </form>

</div>

<!-- ===================== SIMPLE JS ACTIONS ===================== -->
<script>
function openEditModal(id, name) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

function toggleDepartment(id) {
    fetch('/settings/departments/deactivate', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(() => location.reload());
}

function deleteDepartment(id) {
    if (!confirm('Are you sure you want to delete this department?')) return;

    fetch('/settings/departments/delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(() => location.reload());
}
</script>