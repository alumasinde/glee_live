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

    <form method="POST" action="/reset-password" class="login-card" autocomplete="off">

        <h2>Reset Password</h2>

        <input type="hidden" name="token" value="<?= $token ?>">
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
            <label for="password">New Password</label>
            <div class="input-icon">
                <i class="fa-solid fa-lock"></i>
                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Your new password"
                    required
                    autocomplete="new-password">
                <button type="button"
                    class="reveal-toggle"
                    data-target="password"
                    aria-label="Show password"
                    aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirm Password</label>
            <div class="input-icon">
                <i class="fa-solid fa-lock"></i>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    placeholder="Confirm your new password"
                    required
                    autocomplete="new-password">
                <button type="button"
                    class="reveal-toggle"
                    data-target="password_confirmation"
                    aria-label="Show password"
                    aria-pressed="false">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="btn-login">
            Reset Password
        </button>

        <div class="extra-links">
            <a href="/login">Back to login</a>
        </div>

    </form>

    <p class="login-footer">Gatepass Management System</p>

</div>

<script>
    document.querySelectorAll('.reveal-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var input = document.getElementById(btn.dataset.target);
            var showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            btn.setAttribute('aria-pressed', showing ? 'true' : 'false');
            btn.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
            btn.querySelector('i').className = showing ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            input.focus();
        });
    });

    document.querySelector('.login-card').addEventListener('submit', function() {
        var btn = this.querySelector('.btn-login');
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Resetting\u2026';
    });
</script>
