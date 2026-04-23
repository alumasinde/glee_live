<h1 class="page-heading">Roles Management</h1>

<a href="/roles/create" class="btn btn-primary mb-3">
    <i class="fa-solid fa-plus"></i> Create Role
</a>

<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th>#</th>
            <th>Role Name</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($roles as $role): ?>
        <tr>
            <td><?= $role['id'] ?></td>
            <td><?= htmlspecialchars($role['name']) ?></td>
            <td>
                <a href="/roles/<?= $role['id'] ?>/edit" class="btn btn-sm btn-warning">Edit</a>
                <a href="/roles/<?= $role['id'] ?>/permissions" class="btn btn-sm btn-info">Permissions</a>
                <form action="/roles/<?= $role['id'] ?>/delete" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this role?')">
                    <button class="btn btn-sm btn-danger">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>