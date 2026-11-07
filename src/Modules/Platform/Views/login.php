<div class="platform-login">

    <div class="card">

        <h1 class="page-heading">Platform sign in</h1>

        <p class="platform-meta">
            Provider access only. Tenant accounts cannot sign in here, and this
            session never grants tenant permissions.
        </p>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="/platform/login">

            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

            <div class="form-group">
                <label class="gp-label" for="platform-email">Email</label>
                <input class="gp-input"
                       id="platform-email"
                       name="email"
                       type="email"
                       value="<?= htmlspecialchars((string) ($email ?? '')) ?>"
                       autocomplete="username"
                       required
                       autofocus>
            </div>

            <div class="form-group">
                <label class="gp-label" for="platform-password">Password</label>
                <input class="gp-input"
                       id="platform-password"
                       name="password"
                       type="password"
                       autocomplete="current-password"
                       required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Sign in</button>
            </div>

        </form>

    </div>

</div>
