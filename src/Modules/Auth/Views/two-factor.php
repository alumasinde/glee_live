<?php
$logo   = $company_logo ?? '';
$logoPath = $logo !== '' ? base_path('public/uploads/tenants/' . basename($logo)) : null;
?>

<div class="login-wrapper">

    <div class="login-brand">
        <?php if ($logoPath && is_file($logoPath)): ?>
            <img class="tenant-logo"
                src="/uploads/tenants/<?= htmlspecialchars(basename($logo)) ?>"
                alt="<?= htmlspecialchars($tenant_name ?? 'Company') ?> logo">
        <?php endif; ?>

        <?php if (!empty($tenant_name)): ?>
            <div class="brand-name"><?= htmlspecialchars($tenant_name) ?></div>
        <?php endif; ?>
    </div>

    <form method="POST" action="/login/two-factor" class="login-card" id="mfa-form">

        <h2>Two-factor verification</h2>

        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="code">Authenticator code</label>
            <div class="input-icon">
                <i class="fa-solid fa-shield-halved"></i>
                <input
                    id="code"
                    type="text"
                    name="code"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    maxlength="10"
                    placeholder="123456"
                    required
                    autofocus
                    autocomplete="one-time-code">
            </div>
            <p class="field-help">
                Enter the 6-digit code from your authenticator app, or one of your
                recovery codes if you no longer have it.
            </p>
        </div>

        <button type="submit" class="btn-login" data-loading-label="Verifying…">Verify and sign in</button>

        <div class="extra-links">
            <a href="/login">Use a different account</a>
        </div>

    </form>

    <p class="login-footer">Gatepass Management System</p>

</div>

