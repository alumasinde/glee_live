<h2>Email (SMTP) Settings</h2>

<?php if (!empty($notice)): ?>
    <p><strong><?= htmlspecialchars($notice) ?></strong></p>
<?php endif; ?>

<form method="POST" action="/settings/email">

    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

    <label>
        <input type="checkbox" name="enabled" value="1"
            <?= $config['enabled'] ? 'checked' : '' ?>>
        Enable outgoing email
    </label>

    <br><br>

    <label>SMTP Host</label>
    <input type="text" name="host"
        value="<?= htmlspecialchars($config['host']) ?>"
        placeholder="smtp.example.com">

    <br><br>

    <label>Port</label>
    <input type="number" name="port"
        value="<?= (int) $config['port'] ?>" min="1" max="65535">

    <br><br>

    <label>Encryption</label>
    <select name="encryption">
        <?php foreach (['tls' => 'STARTTLS', 'ssl' => 'Implicit SSL', 'none' => 'None'] as $value => $label): ?>
            <option value="<?= $value ?>" <?= $config['encryption'] === $value ? 'selected' : '' ?>>
                <?= $label ?>
            </option>
        <?php endforeach; ?>
    </select>

    <br><br>

    <label>Username</label>
    <input type="text" name="username"
        value="<?= htmlspecialchars($config['username']) ?>"
        autocomplete="off">

    <br><br>

    <label>Password</label>
    <input type="password" name="password" value=""
        placeholder="<?= $config['has_password'] ? 'Stored — enter a new value to replace' : 'SMTP password' ?>"
        autocomplete="new-password">

    <br><br>

    <label>From Address</label>
    <input type="email" name="from_address"
        value="<?= htmlspecialchars($config['from_address']) ?>"
        placeholder="gatepass@example.com">

    <br><br>

    <label>From Name</label>
    <input type="text" name="from_name"
        value="<?= htmlspecialchars($config['from_name']) ?>">

    <br><br>

    <label>
        <input type="checkbox" name="insecure_tls" value="1"
            <?= !empty($config['insecure_tls']) ? 'checked' : '' ?>>
        Allow self-signed TLS certificates (internal servers only)
    </label>

    <br><br>

    <button type="submit">Save Settings</button>

    <a href="/settings" class="btn btn-secondary">
        Back
    </a>

</form>

<hr>

<h3>Send Test Email</h3>

<form method="POST" action="/settings/email/test">

    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

    <label>Recipient</label>
    <input type="email" name="test_to" required placeholder="you@example.com">

    <br><br>

    <button type="submit">Send Test</button>

</form>
