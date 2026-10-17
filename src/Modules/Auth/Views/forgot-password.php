<form method="POST" action="/forgot-password" class="auth-form">

    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

    <h2>Forgot Password</h2>

    <?php if (!empty($success)): ?>
        <div class="alert success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <input type="email" name="email" placeholder="Enter your email" required>

    <button type="submit">Send Reset Link</button>

</form>