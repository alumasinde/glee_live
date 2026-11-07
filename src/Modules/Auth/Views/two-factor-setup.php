<?php
/**
 * Two-factor enrolment: scan (setup), confirm and show one-time recovery codes
 * (codes), or manage an active enrolment (enabled).
 *
 * @var string      $state  setup|codes|enabled
 * @var string|null $secret formatted base32 key
 * @var string|null $qr     data-URI SVG of the otpauth:// QR
 * @var string[]    $recovery_codes plaintext, shown exactly once
 */

use App\Middleware\CSRFMiddleware;

$state = $state ?? 'setup';
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars($title ?? 'Two-Factor Authentication') ?>
    </h1>
    <div class="page-actions">
        <a class="btn btn-sm btn-outline" href="/notifications/preferences">Notification settings</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="gp-alert gp-alert--danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($saved)): ?>
    <div class="gp-alert gp-alert--success"><?= htmlspecialchars($saved) ?></div>
<?php endif; ?>

<div class="table-card u-maxw-720">
    <div class="table-card-head">
        <span class="table-card-title">
            <?= $state === 'enabled' ? 'Two-factor is active' : ($state === 'codes' ? 'Save your recovery codes' : 'Scan to enrol') ?>
        </span>
    </div>
    <div class="card-body">

        <?php if ($state === 'setup'): ?>
            <p class="form-text u-mt-0">
                Add this key to Google Authenticator, 1Password, Authy or any TOTP app, then confirm
                the 6-digit code. The set-up expires in a few minutes; reload this page to start again.
            </p>

            <div class="d-flex gap-4 align-items-start flex-wrap">
                <?php if (!empty($qr)): ?>
                    <img src="<?= htmlspecialchars($qr) ?>" width="220" height="220"
                         alt="QR code for authenticator app enrolment">
                <?php endif; ?>

                <div>
                    <div class="cell-main u-ff-mono u-tracking-1">
                        <?= htmlspecialchars($secret ?? '') ?>
                    </div>
                    <div class="cell-date">Enter this key manually if you cannot scan.</div>
                </div>
            </div>

            <form method="POST" action="/settings/security/two-factor" class="u-mt-s4 u-maxw-420">
                <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">

                <div class="form-group">
                    <label for="password">Confirm your password</label>
                    <input class="form-control" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <div class="form-group">
                    <label for="code">Authenticator code</label>
                    <input class="form-control" type="text" id="code" name="code"
                           inputmode="numeric" maxlength="10" placeholder="123456"
                           autocomplete="one-time-code" required>
                </div>

                <button type="submit" class="btn btn-primary">Turn on two-factor</button>
            </form>

        <?php elseif ($state === 'codes'): ?>
            <div class="gp-alert gp-alert--warning">
                These codes are shown once. Store them somewhere other than your phone;
                each one works only one time if you lose your authenticator app.
            </div>

            <div class="table-responsive">
                <table class="table u-maxw-420">
                    <tbody>
                        <?php foreach ($recovery_codes ?? [] as $code): ?>
                            <tr>
                                <td class="u-ff-mono u-tracking-1">
                                    <?= htmlspecialchars($code) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-actions u-mt-s3">
                <a class="btn btn-primary" href="/settings/security/two-factor">I have saved these codes</a>
            </div>

        <?php else: ?>
            <ul class="u-m-0 u-mb-s4 u-pl-12rem">
                <li>Enabled on <?= htmlspecialchars(format_date($enabled_at ?? null) ?: '—') ?></li>
                <li>
                    <?= (int) ($recovery_remaining ?? 0) ?> recovery
                    <?= (int) ($recovery_remaining ?? 0) === 1 ? 'code' : 'codes' ?> left.
                    Turning two-factor off again discards them.
                </li>
            </ul>

            <form method="POST" action="/settings/security/two-factor/disable"
                  class="u-maxw-420">
                <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">

                <div class="form-group">
                    <label for="password">Password</label>
                    <input class="form-control" type="password" id="password" name="password"
                           autocomplete="current-password" required>
                </div>

                <div class="form-group">
                    <label for="code">Current code or recovery code</label>
                    <input class="form-control" type="text" id="code" name="code"
                           maxlength="11" placeholder="123456 or K7QPM-3XRBW" required>
                </div>

                <button type="submit" class="btn btn-outline">Turn off two-factor</button>
            </form>
        <?php endif; ?>

    </div>
</div>
