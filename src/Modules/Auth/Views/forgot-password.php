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

    <form method="POST" action="/forgot-password" class="login-card" autocomplete="off">

        <h2>Forgot Password</h2>

        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

        <?php if (!empty($_SESSION['flash'])): ?>
            <?php
                $type    = $_SESSION['flash']['type'] ?? 'info';
                $message = $_SESSION['flash']['message'] ?? '';
                unset($_SESSION['flash']);
            ?>
            <?php if ($message !== ''): ?>
                <div class="alert-error <?= $type === 'success' ? 'alert-success' : '' ?>">
                    <i class="fa-solid <?= $type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>
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
                    autocomplete="email">
            </div>
        </div>

        <button type="submit" class="btn-login">
            Send Reset Link
        </button>

        <div class="extra-links">
            <a href="/login">Back to login</a>
        </div>

    </form>

    <p class="login-footer">Gatepass Management System</p>

</div>