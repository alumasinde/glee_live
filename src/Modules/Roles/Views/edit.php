<h1 class="page-heading">Edit Role</h1>

<form method="POST" action="/roles/<?= $role['id'] ?>">
    <div class="form-group mb-3">
        <label>Role Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($role['name']) ?>" class="form-control" required>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <i class="fa-solid fa-save"></i> Update Role
        </button>
        <a href="/roles" class="btn btn-secondary">Cancel</a>
    </div>
</form>