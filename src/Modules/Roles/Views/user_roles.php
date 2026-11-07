<?php

/**
 * Assign roles to a single user.
 *
 * UserRoleController::index() has always rendered this view, but the file was
 * missing, so GET /users/{id}/roles answered 500 "View not found".
 */

use App\Middleware\CSRFMiddleware;

$assigned  = array_flip($assignedRoleIds);
$canAssign = can('roles.assign');

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-user-tag"></i> Assign Roles
    </h1>

    <div class="page-actions">
        <a href="/settings/users" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Users
        </a>
    </div>
</div>

<div class="table-card">
    <div class="table-card-head">
        <span class="table-card-title">Roles for user #<?= (int) $userId ?></span>
    </div>

    <div class="card-body">
        <?php if (empty($roles)): ?>
            <p class="form-text u-m-0">
                No roles exist in this workspace yet. Create one first.
            </p>
        <?php else: ?>
            <form method="POST" action="/users/<?= (int) $userId ?>/roles">
                <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">

                <?php foreach ($roles as $role): ?>
                    <div class="form-group">
                        <label class="form-check">
                            <input class="form-check-input"
                                   type="checkbox"
                                   name="roles[]"
                                   value="<?= (int) $role['id'] ?>"
                                   id="role-<?= (int) $role['id'] ?>"
                                   <?= isset($assigned[(int) $role['id']]) ? 'checked' : '' ?>
                                   <?= $canAssign ? '' : 'disabled' ?>>
                            <span class="form-check-label"><?= htmlspecialchars($role['name']) ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" <?= $canAssign ? '' : 'disabled title="Requires roles.assign"' ?>>
                        Save Roles
                    </button>
                    <a href="/settings/users" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
