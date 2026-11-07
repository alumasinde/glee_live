<?php

use App\Core\Auth;
use App\Core\View;

$error  = $_GET['error'] ?? null;
$status = $status ?? 'all';
$selfId = Auth::id();

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-users"></i> User Management
    </h1>

    <div class="page-actions">
        <?php if (can('users.create')): ?>
            <a href="/settings/users/create" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Add User
            </a>
        <?php endif; ?>
        <a href="/settings" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php if ($error): ?>
    <div class="gp-alert gp-alert--danger u-maxw-900">
        <?= htmlspecialchars((string) $error) ?>
    </div>
<?php endif; ?>

<form method="GET" action="/settings/users" class="filter-bar">
    <div class="filter-grid">
        <div class="form-group">
            <label for="status">Status</label>
            <select class="form-select" id="status" name="status">
                <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All users</option>
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active only</option>
                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive only</option>
            </select>
        </div>
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        <a class="btn btn-sm btn-outline" href="/settings/users">Reset</a>
    </div>
</form>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($users),
        'noun'  => $status === 'inactive' ? 'inactive user' : ($status === 'active' ? 'active user' : 'user'),
    ]); ?>

    <?php if (empty($users)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-users"></i>
            <div class="empty-state-title">
                <?= $status === 'inactive'
                    ? 'No inactive users'
                    : ($status === 'active' ? 'No active users' : 'No users found') ?>
            </div>
            <div class="empty-state-text">
                <?php if ($status === 'inactive'): ?>
                    Every account in this workspace is active.
                <?php elseif ($status === 'active'): ?>
                    No active accounts match this filter.
                <?php else: ?>
                    Add a user to give someone access to this workspace.
                <?php endif; ?>
            </div>
            <?php if ($status !== 'all'): ?>
                <a href="/settings/users" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-rotate-left"></i> Show all users
                </a>
            <?php endif; ?>
            <?php if (can('users.create')): ?>
                <a href="/settings/users/create" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Add User
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Username</th>
                        <th>Department</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th class="text-end" width="110">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $userId     = (int) $user['id'];
                        $isActive   = (int) $user['is_active'] === 1;
                        $isSelf     = $selfId === $userId;
                        $fullName   = trim($user['first_name'] . ' ' . $user['last_name']);
                        ?>
                        <tr>
                            <td>
                                <span class="cell-main"><?= htmlspecialchars($fullName) ?></span>
                                <?php if ($isSelf): ?>
                                    <span class="cell-sub">(you)</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($user['email']) ?></td>
                            <td><span class="cell-mono"><?= htmlspecialchars($user['username']) ?></span></td>
                            <td>
                                <?php if (!empty($user['department_name'])): ?>
                                    <span class="cell-sub"><?= htmlspecialchars($user['department_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info">
                                    <?= htmlspecialchars($user['roles'] ?? '—') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($isActive): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions justify-content-end">
                                <?php if (can('users.update')): ?>
                                    <a href="/settings/users/<?= $userId ?>/edit"
                                       class="btn btn-sm btn-ghost btn-icon" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (can('users.disable') && !$isSelf): ?>
                                    <form method="POST"
                                          action="/settings/users/<?= $userId ?>/<?= $isActive ? 'deactivate' : 'activate' ?>"
                                          class="table-action-button">
                                        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                        <button type="submit"
                                                class="btn btn-sm btn-ghost btn-icon"
                                                title="<?= $isActive ? 'Deactivate' : 'Activate' ?>"
                                                data-confirm="<?= $isActive
                                                    ? 'Deactivate ' . htmlspecialchars($fullName, ENT_QUOTES) . '? They will be signed out and cannot sign in until reactivated.'
                                                    : 'Reactivate ' . htmlspecialchars($fullName, ENT_QUOTES) . '?' ?>">
                                            <i class="fa-solid <?= $isActive ? 'fa-user-slash text-danger' : 'fa-user-check' ?>"></i>
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
