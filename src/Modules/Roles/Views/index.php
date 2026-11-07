<?php

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-shield-halved"></i> Roles
    </h1>

    <div class="page-actions">
        <?php if (can('roles.create')): ?>
            <a href="/roles/create" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Create Role
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($roles),
        'noun'  => 'role',
    ]); ?>

    <?php if (empty($roles)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-shield-halved"></i>
            <div class="empty-state-title">No roles yet</div>
            <div class="empty-state-text">
                Roles group permissions and control what each user can see.
            </div>
            <a href="/roles/create" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Create Role
            </a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th class="text-end" width="230">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role): ?>
                        <?php $id = (int) $role['id']; ?>
                        <tr>
                            <td>
                                <span class="cell-main"><?= htmlspecialchars($role['name']) ?></span>
                                <span class="cell-sub">#<?= $id ?></span>
                            </td>
                            <td class="table-actions justify-content-end">
                                <a href="/roles/<?= $id ?>/permissions" class="btn btn-sm btn-secondary">
                                    <i class="fa-solid fa-key"></i> Permissions
                                </a>
                                <?php if (can('roles.update')): ?>
                                    <a href="/roles/<?= $id ?>/edit" class="btn btn-sm btn-ghost btn-icon" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (can('roles.delete')): ?>
                                    <form action="/roles/<?= $id ?>/delete" method="POST" class="table-action-button"
                                          data-confirm="Delete this role?">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button class="btn btn-sm btn-ghost btn-icon" title="Delete">
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
