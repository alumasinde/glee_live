<?php
$logo   = $company_logo ?? '';
$logoPath = $logo !== '' ? base_path('public/uploads/tenants/' . basename($logo)) : null;

$initials = '';
if (!empty($tenant_name)) {
    $words = preg_split('/\s+/', trim($tenant_name));
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= mb_strtoupper(mb_substr($w, 0, 1));
    }
}
?>

<div class="login-wrapper">

    <div class="login-brand">
        <?php if ($logoPath && is_file($logoPath)): ?>
            <img class="tenant-logo"
                src="/uploads/tenants/<?= htmlspecialchars(basename($logo)) ?>"
                alt="<?= htmlspecialchars($tenant_name ?? 'Company') ?> logo">
        <?php elseif ($initials !== ''): ?>
            <div class="brand-avatar"><?= htmlspecialchars($initials) ?></div>
        <?php endif; ?>

        <?php if (!empty($tenant_name)): ?>
            <div class="brand-name"><?= htmlspecialchars($tenant_name) ?></div>
        <?php endif; ?>
    </div>

    <form method="POST" action="/login" class="login-card" autocomplete="off" id="login-form">

        <h2>Sign in</h2>

        <input
            type="hidden"
            name="_token"
            value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="email">Email</label>
            <div class="input-icon">
                <i class="fa-solid fa-envelope"></i>
                <input
                    id="email"
                    type="email"
                    name="email"
                    placeholder="you@company.com"
                    required
                    autofocus
                    autocomplete="username">
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-icon">
                <i class="fa-solid fa-lock"></i>
                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Your password"
                    required
                    autocomplete="current-password">
                <button type="button"
                    class="reveal-toggle"
                    data-target="password"
                    aria-label="Show password"
                    aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login" data-loading-label="Signing in…">
            Sign in
        </button>

        <div class="extra-links">
            <a href="/forgot-password">Forgot password?</a>
        </div>

    </form>

    <p class="login-footer">Gatepass Management System</p>

</div>

