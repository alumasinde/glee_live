<?php
// Convert applied permissions to fast lookup map
$appliedMap = array_flip($rolePermissions);

// Group permissions by module
$permissionsByModule = [];
foreach ($allPermissions as $perm) {
    $permissionsByModule[$perm['module']][] = $perm;
}

$totalApplied = count($appliedMap);
$canAssign    = can('roles.assign');
$restrictedKeys = array_flip($restrictedPermissions ?? []);
?>

<h1 class="page-heading">
    Assign Permissions: <?= htmlspecialchars($role['name']) ?>
</h1>

<div class="summary-bar">
    <span class="summary-count">
        <?= $totalApplied ?> permission(s) applied
    </span>
</div>

<?php if (!empty($restrictedKeys)): ?>
    <p class="platform-meta">
        Permissions marked <strong>Platform-managed</strong> are controlled by the platform and cannot be granted here.
    </p>
<?php endif; ?>

<form method="POST" action="/roles/<?= (int)$role['id'] ?>/permissions">
<input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

<?php foreach ($permissionsByModule as $module => $perms): 
    $moduleKey = md5($module);

    $moduleApplied = 0;
    foreach ($perms as $perm) {
        if (isset($appliedMap[$perm['id']])) {
            $moduleApplied++;
        }
    }
?>

    <div class="card">

        <div class="card-header"
             data-module="<?= $moduleKey ?>">

            <div class="module-title">
                <strong><?= htmlspecialchars($module) ?></strong>
                <span class="badge badge-total">
                    <?= count($perms) ?>
                </span>

                <?php if ($moduleApplied > 0): ?>
                    <span class="badge badge-applied">
                        <?= $moduleApplied ?> applied
                    </span>
                <?php endif; ?>
            </div>

            <div class="module-actions">
                <label>
                    <input type="checkbox"
                           class="toggle-module"
                           <?= $canAssign ? '' : 'disabled' ?>
                           data-module="<?= $moduleKey ?>">
                    Toggle All
                </label>
            </div>
        </div>

        <div class="card-body module-body module-<?= $moduleKey ?>
            <?= $moduleApplied > 0 ? 'expanded' : '' ?>">

            <?php foreach ($perms as $perm): 
                $isApplied = isset($appliedMap[$perm['id']]);
                $permKey   = strtolower($perm['module'] . '.' . $perm['action']);
                $isRestricted = isset($restrictedKeys[$permKey]);
            ?>
                <div class="permission-item <?= $isApplied ? 'applied' : '' ?>">

                    <input type="checkbox"
                           class="permission-checkbox module-<?= $moduleKey ?>"
                           <?= ($canAssign && !$isRestricted) ? '' : 'disabled' ?>
                           <?= $isRestricted ? 'title="Restricted by platform"' : '' ?>
                           name="permissions[]"
                           value="<?= (int)$perm['id'] ?>"
                           id="perm-<?= (int)$perm['id'] ?>"
                           <?= $isApplied ? 'checked' : '' ?>>

                    <label for="perm-<?= (int)$perm['id'] ?>">
                        <?= htmlspecialchars($perm['action']) ?>
                    </label>

                    <?php if ($isRestricted): ?>
                        <span class="badge badge-total">Platform-managed</span>
                    <?php endif; ?>

                    <?php if ($isApplied): ?>
                        <span class="badge badge-applied-small">
                            Applied
                        </span>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>

        </div>
    </div>

<?php endforeach; ?>

    <div class="form-actions">
        <button type="submit" class="btn-primary" <?= $canAssign ? '' : 'disabled title="Requires roles.assign"' ?>>
            Save Permissions
        </button>
        <a href="/roles" class="btn-secondary">Cancel</a>
    </div>

</form>

<script src="/assets/js/pages/role-permissions.js" defer></script>
