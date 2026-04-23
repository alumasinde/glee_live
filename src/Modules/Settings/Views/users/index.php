<div class="page-header">

<h1 class="page-heading">
    <i class="fa-solid fa-users"></i> User Management
</h1>

<div class="page-actions">
    <a href="/settings/users/create" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Add User
    </a>

      <a href="/settings" class="btn btn-secondary">
            Back
        </a>
</div>
</div>  

    <div class="table-card">
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Username</th>
                    <th>Roles</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= $user['id'] ?></td>
                            <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td>
                                <span class="badge bg-info text-dark">
                                    <?= htmlspecialchars($user['roles'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="table-actions">
                                <a href="/settings/users/<?= $user['id'] ?>/edit"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            No users found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
          
</div>