<form method="POST" action="/reset-password">

    <input type="hidden" name="token" value="<?= $token ?>">

    <h2>Reset Password</h2>

    <?php if (!empty($error)): ?>
        <div style="color:red"><?= $error ?></div>
    <?php endif; ?>

    <input type="password" name="password" placeholder="New Password" required>

    <button>Reset</button>

</form>
