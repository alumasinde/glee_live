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
             <label class="form-label">Department Code</label>
             <input type="text"
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

<!-- ===================== LIST ===================== -->
<div class="table-card">

    <h3 style="margin-bottom: 15px;">Department List</h3>

    <?php if (!empty($departments)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
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
                            <?= htmlspecialchars($dept->code) ?>
                        </td>
                      <td>
    <label class="toggle-switch">
        <input type="checkbox"
               <?= $dept->isActive ? 'checked' : '' ?>
               onchange="toggleDepartment(<?= $dept->id ?>)">
        <span class="slider"></span>
    </label>
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
    fetch('/settings/departments/toggle', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
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
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id})
    }).then(() => location.reload());
}
</script>