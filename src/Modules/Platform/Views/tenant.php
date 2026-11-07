<h1 class="page-heading"><?= htmlspecialchars((string) $tenant['name']) ?></h1>

<p class="platform-meta">
    Code <strong><?= htmlspecialchars((string) $tenant['code']) ?></strong>
    &middot; <?= htmlspecialchars((string) ($tenant['email'] ?? '')) ?>
    &middot; <?= !empty($tenant['is_active']) ? 'Active' : 'Suspended' ?>
</p>

<p class="platform-note">
    Disabling a module hides its routes and navigation but never deletes data.
    Re-enabling restores the module immediately. Core modules are always on.
</p>

<div class="card">
    <h2 class="card-title">Status</h2>

    <?php if (!empty($tenant['is_active'])): ?>
        <p class="platform-meta">
            Active. Suspending signs out every tenant user, ends any open support
            access, and blocks sign-in until the tenant is reactivated. Data and
            module settings are kept.
        </p>

        <form method="POST" action="/platform/tenants/<?= (int) $tenant['id'] ?>/suspend">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

            <div class="form-group">
                <label class="gp-label" for="suspend-reason">Reason (optional)</label>
                <input class="gp-input" id="suspend-reason" type="text" name="reason"
                       maxlength="255" placeholder="Ticket / reason recorded in the platform audit">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-danger">Suspend tenant</button>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-warning">
            <strong>Suspended.</strong> Every tenant user has been signed out, open
            support access is ended, and sign-in is blocked. Reactivating restores
            access; users must sign in again.
        </div>

        <form method="POST" action="/platform/tenants/<?= (int) $tenant['id'] ?>/reactivate">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Reactivate tenant</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<form method="POST" action="/platform/tenants/<?= (int) $tenant['id'] ?>/modules">

    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

    <div class="card">
        <h2 class="card-title">Modules</h2>

        <div class="module-grid">
        <?php foreach ($modules as $module): ?>
            <?php
            $name      = (string) $module['name'];
            $isCore    = !empty($module['is_core']);
            $dependsOn = trim((string) ($module['depends_on'] ?? ''));
            $enabled   = !empty($states[$name]);
            ?>
            <label class="module-toggle <?= $isCore ? 'is-core' : '' ?>">
                <input type="checkbox"
                       name="modules[]"
                       value="<?= htmlspecialchars($name) ?>"
                       <?= $enabled ? 'checked' : '' ?>
                       <?= $isCore ? 'disabled' : '' ?>>

                <span>
                    <strong><?= htmlspecialchars($name) ?></strong>

                    <?php if ($isCore): ?>
                        <span class="badge badge-total">Core</span>
                    <?php endif; ?>

                    <?php if ($dependsOn !== ''): ?>
                        <small>Requires <?= htmlspecialchars($dependsOn) ?></small>
                    <?php endif; ?>
                </span>
            </label>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save modules</button>
        <a class="btn btn-secondary" href="/platform">Back</a>
    </div>

</form>

<div class="card">
    <h2 class="card-title">Support access</h2>

    <p class="platform-meta">
        Sign in as a tenant user without their password. Each start and end is
        audited, the session expires after <?= (int) $supportTtl ?> minutes, and
        returning restores your platform session. Only active platform superadmins
        can start one.
    </p>

    <?php if (empty($tenant['is_active'])): ?>
        <div class="alert alert-warning">
            Support access is unavailable while the tenant is suspended.
        </div>
    <?php endif; ?>

    <?php if (!empty($support)): ?>
        <div class="alert alert-info">
            A support session is active
            <?php if ((int) ($support['tenant_id'] ?? 0) === (int) $tenant['id']): ?>
                for this tenant
            <?php else: ?>
                in another tenant
            <?php endif; ?>.
            Return to the platform before starting another one.
        </div>

        <form method="POST" action="/platform/impersonation/stop" class="u-mb-s3">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
            <button type="submit" class="btn btn-primary">Return to platform</button>
        </form>
    <?php endif; ?>

    <?php if (empty($users)): ?>
        <p class="platform-meta">This tenant has no users yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Roles</th>
                        <th>Status</th>
                        <th class="text-end">Support</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <?php $available = !empty($tenant['is_active']) && !empty($user['is_active']); ?>
                    <tr>
                        <td>
                            <span class="cell-main">
                                <?= htmlspecialchars(trim((string) $user['first_name'] . ' ' . (string) $user['last_name'])) ?>
                            </span>
                            <span class="cell-sub"><?= htmlspecialchars((string) $user['email']) ?></span>
                        </td>
                        <td><?= htmlspecialchars((string) ($user['roles'] ?? '—')) ?></td>
                        <td>
                            <span class="badge badge-<?= !empty($user['is_active']) ? 'approved' : 'rejected' ?>">
                                <?= !empty($user['is_active']) ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <?php if ($available && empty($support)): ?>
                                <form method="POST"
                                      action="/platform/tenants/<?= (int) $tenant['id'] ?>/impersonate"
                                      class="platform-impersonate-form">
                                    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                    <input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                                    <input class="gp-input" type="text" name="reason" maxlength="255"
                                           placeholder="Ticket / reason (optional)">
                                    <button type="submit" class="btn btn-secondary">Impersonate</button>
                                </form>
                            <?php else: ?>
                                <span class="platform-meta">Unavailable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
