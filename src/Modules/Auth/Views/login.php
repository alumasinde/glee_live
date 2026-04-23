

<body class="login-page">
<div class="login-wrapper">

    <form method="POST" action="/login" class="login-card" autocomplete="off">

        <!-- CSRF Token -->
        <input 
            type="hidden" 
            name="csrf_token" 
            value="<?= htmlspecialchars($csrf_token ?? '', ENT_QUOTES, 'UTF-8') ?>"
            >

        <?php if (!empty($error)): ?>
            <div class="alert-error">
                <i class="fa fa-exclamation-circle"></i>
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="form-group">
            <label for="email">Email</label>
            <div class="input-icon">
                <i class="fa fa-envelope"></i>
                <input 
                    id="email"
                    type="email" 
                    name="email" 
                    placeholder="Enter your email" 
                    required
                    autocomplete="username"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <div class="input-icon">
                <i class="fa fa-lock"></i>
                <input 
                    id="password"
                    type="password" 
                    name="password" 
                    placeholder="Enter your password" 
                    required
                    autocomplete="current-password"
                >
            </div>
        </div>

        <button type="submit" class="btn-login">
            <i class="fa fa-sign-in-alt"></i> Login
        </button>

        <div class="extra-links">
            <a href="/forgot-password">Forgot password?</a>
        </div>

    </form>

</div>
</body>