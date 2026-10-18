<?php

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-users"></i> User Management
    </h1>

    <div class="page-actions">
        <a href="/settings/users/create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Add User
        </a>
        <a href="/settings" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($users),
        'noun'  => 'user',
    ]); ?>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users"></i>
            <div class="empty-state-title">No users found</div>
            <div class="empty-state-text">
                Add a user to give someone access to this workspace.
            </div>
            <a href="/settings/users/create" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Add User
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Roles</th>
                        <th class="text-end" width="80">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <span class="cell-main"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($user['email']) ?></td>
                            <td><span class="cell-mono"><?= htmlspecialchars($user['username']) ?></span></td>
                            <td>
                                <span class="badge badge-info">
                                    <?= htmlspecialchars($user['roles'] ?? '—') ?>
                                </span>
                            </td>
                            <td class="table-actions justify-content-end">
                                <a href="/settings/users/<?= (int) $user['id'] ?>/edit"
                                   class="btn btn-sm btn-ghost btn-icon" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
