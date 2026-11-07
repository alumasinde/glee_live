<?php $restrictedMap = array_flip($restricted); ?>

<h1 class="page-heading">Features &amp; permissions</h1>

<p class="platform-meta">
    Features are global: turning one off closes it for every tenant, including any
    public endpoint it exposes. Restricted permissions are withheld from tenant
    roles at runtime, even where an older grant still exists.
</p>

<form method="POST" action="/platform/features">

    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

    <div class="card">
        <h2 class="card-title">Global features</h2>

        <?php foreach ($features as $key => $feature): ?>
            <label class="module-toggle">
                <input type="checkbox"
                       name="features[<?= htmlspecialchars($key) ?>]"
                       value="1"
                       <?= !empty($feature['enabled']) ? 'checked' : '' ?>>

                <span>
                    <strong><?= htmlspecialchars($feature['label']) ?></strong>
                    <?php if ($feature['description'] !== ''): ?>
                        <small><?= htmlspecialchars($feature['description']) ?></small>
                    <?php endif; ?>
                </span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h2 class="card-title">Platform-managed permissions</h2>

        <p class="platform-meta">
            Checked permissions become read-only in every tenant role matrix and are
            denied at runtime. Unchecked permissions stay tenant-controlled.
        </p>

        <div class="module-grid">
        <?php foreach ($matrix as $module => $permissions): ?>
            <div class="module-card">
                <strong><?= htmlspecialchars((string) $module) ?></strong>

                <?php foreach ($permissions as $permission): ?>
                    <?php $key = (string) $permission['permission_key']; ?>
                    <label class="permission-option">
                        <input type="checkbox"
                               name="restricted_permissions[]"
                               value="<?= htmlspecialchars($key) ?>"
                               <?= isset($restrictedMap[$key]) ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars((string) $permission['action']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save policy</button>
    </div>

</form>
