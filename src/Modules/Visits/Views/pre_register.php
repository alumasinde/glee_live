<?php /** @var array $tenant @var array $options @var bool $done @var array $errors @var array $old */ ?>
<div class="login-wrapper">

    <div class="login-brand">
        <div class="brand-name"><?= htmlspecialchars($tenant['name']) ?></div>
    </div>

    <?php if (!empty($done)): ?>

        <div class="login-card">
            <h2>Request received</h2>
            <p class="extra-links">
                Your pre-registration has been received. If a pass was created,
                a link to your digital QR pass has been sent to your email.
                Please show it at the gate on arrival.
            </p>
        </div>

    <?php else: ?>

        <form method="POST" class="login-card" autocomplete="off">

            <h2>Pre-register your visit</h2>

            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

            <!-- Honeypot: humans never fill this -->
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars(implode(' ', $errors)) ?></span>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="first_name">First name</label>
                <input id="first_name" name="first_name" required maxlength="120"
                    value="<?= htmlspecialchars($old['first_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="last_name">Last name</label>
                <input id="last_name" name="last_name" required maxlength="120"
                    value="<?= htmlspecialchars($old['last_name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Email <span class="input-icon">(pass link is sent here)</span></label>
                <input id="email" type="email" name="email" required maxlength="255"
                    value="<?= htmlspecialchars($old['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="phone">Phone</label>
                <input id="phone" type="tel" name="phone" maxlength="50"
                    value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="department_id">Visiting department</label>
                <select id="department_id" name="department_id" required>
                    <option value="">Select department...</option>
                    <?php foreach ($options['departments'] as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"
                            <?= (int) ($old['department_id'] ?? 0) === (int) $d['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($d['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="host_user_id">Host <span class="input-icon">(optional)</span></label>
                <select id="host_user_id" name="host_user_id">
                    <option value="">No specific host</option>
                    <?php foreach ($options['hosts'] as $h): ?>
                        <option value="<?= (int) $h['id'] ?>"
                            <?= (int) ($old['host_user_id'] ?? 0) === (int) $h['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($h['first_name'] . ' ' . $h['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="expected_in">Arrival date &amp; time</label>
                <input id="expected_in" type="datetime-local" name="expected_in" required
                    min="<?= date('Y-m-d\T00:00', strtotime('-1 day')) ?>"
                    value="<?= htmlspecialchars($old['expected_in'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="expected_out">Expected departure <span class="input-icon">(optional)</span></label>
                <input id="expected_out" type="datetime-local" name="expected_out"
                    value="<?= htmlspecialchars($old['expected_out'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="purpose">Purpose of visit</label>
                <textarea id="purpose" name="purpose" required rows="3" maxlength="500"><?= htmlspecialchars($old['purpose'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn-login">Submit pre-registration</button>

        </form>

    <?php endif; ?>

    <p class="login-footer">Pre-registration · <?= htmlspecialchars($tenant['name']) ?></p>
</div>
