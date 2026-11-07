<h1 class="page-heading">New tenant</h1>

<p class="platform-meta">
    Creates the tenant, an <strong>admin</strong> role holding the current permission
    catalogue, and its first administrator. Platform-restricted permissions remain
    withheld at runtime. Gatepass types, workflows, gates and branding stay for the
    tenant to configure.
</p>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars((string) $error) ?></div>
<?php endif; ?>

<form method="POST" action="/platform/tenants">

    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

    <div class="card">
        <h2 class="card-title">Tenant</h2>

        <div class="platform-form-grid">
            <div class="form-group">
                <label class="gp-label" for="tenant-name">Name</label>
                <input class="gp-input" id="tenant-name" name="name" required
                       maxlength="255" value="<?= htmlspecialchars((string) ($old['name'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label class="gp-label" for="tenant-code">Subdomain code</label>
                <input class="gp-input" id="tenant-code" name="code" required
                       maxlength="120" pattern="[a-z0-9][a-z0-9-]*"
                       placeholder="acme"
                       value="<?= htmlspecialchars((string) ($old['code'] ?? '')) ?>">
                <small>Lowercase letters, numbers and hyphens.</small>
            </div>

            <div class="form-group">
                <label class="gp-label" for="tenant-email">Contact email</label>
                <input class="gp-input" id="tenant-email" name="email" type="email" required
                       maxlength="255" value="<?= htmlspecialchars((string) ($old['email'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label class="gp-label" for="tenant-phone">Phone</label>
                <input class="gp-input" id="tenant-phone" name="phone" required
                       maxlength="50" value="<?= htmlspecialchars((string) ($old['phone'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label class="gp-label" for="tenant-country">Country</label>
                <input class="gp-input" id="tenant-country" name="country"
                       maxlength="120" value="<?= htmlspecialchars((string) ($old['country'] ?? '')) ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">First administrator</h2>

        <div class="platform-form-grid">
            <div class="form-group">
                <label class="gp-label" for="admin-first">First name</label>
                <input class="gp-input" id="admin-first" name="admin_first_name" required
                       maxlength="120" value="<?= htmlspecialchars((string) ($old['admin_first_name'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label class="gp-label" for="admin-last">Last name</label>
                <input class="gp-input" id="admin-last" name="admin_last_name" required
                       maxlength="120" value="<?= htmlspecialchars((string) ($old['admin_last_name'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label class="gp-label" for="admin-email">Email</label>
                <input class="gp-input" id="admin-email" name="admin_email" type="email" required
                       maxlength="255" value="<?= htmlspecialchars((string) ($old['admin_email'] ?? '')) ?>">
            </div>

            <div class="form-group">
                <label class="gp-label" for="admin-password">Initial password</label>
                <input class="gp-input" id="admin-password" name="admin_password" type="password"
                       required minlength="8" autocomplete="new-password">
                <small>The configured password policy applies (at least 8 characters). The administrator can change it after signing in.</small>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Create tenant</button>
        <a class="btn btn-secondary" href="/platform">Cancel</a>
    </div>

</form>
