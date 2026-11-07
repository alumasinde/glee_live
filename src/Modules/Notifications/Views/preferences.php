<?php
/**
 * Per-event, per-channel notification switches for the signed-in user.
 *
 * @var array<string, array<string, bool>> $grid
 * @var list<array>                        $devices
 * @var string|null                        $saved
 */

use App\Core\Notification\Channel;
use App\Core\Notification\Events;
use App\Middleware\CSRFMiddleware;

$eventLabels = Events::all();
$canUpdate   = can('notifications.update');
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-sliders"></i> <?= htmlspecialchars($title ?? 'Notification Preferences') ?>
    </h1>
    <div class="page-actions">
        <a class="btn btn-sm btn-secondary" href="/notifications">
            <i class="fa-solid fa-bell"></i> Inbox
        </a>
        <a class="btn btn-sm btn-outline" href="/settings/users/profile">My profile</a>
    </div>
</div>

<?php if (!empty($saved)): ?>
    <div class="gp-alert gp-alert--success"><?= htmlspecialchars($saved) ?></div>
<?php endif; ?>

<div class="table-card u-maxw-900">
    <div class="table-card-head">
        <span class="table-card-title">What reaches me</span>
    </div>
    <div class="card-body">
        <p class="form-text u-mt-0">
            Email and in-app are on unless you switch them off. SMS and app push are opt-in:
            SMS needs a phone number on your profile, app push needs a registered device below.
        </p>

        <form method="POST" action="/notifications/preferences">
            <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>When…</th>
                            <?php foreach (Channel::ALL as $channel): ?>
                                <th class="text-center"><?= htmlspecialchars(Channel::label($channel)) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventLabels as $eventKey => $label): ?>
                            <tr>
                                <td><?= htmlspecialchars($label) ?></td>
                                <?php foreach (Channel::ALL as $channel): ?>
                                    <td class="text-center">
                                        <input type="checkbox"
                                               name="preferences[<?= htmlspecialchars($eventKey, ENT_QUOTES) ?>][<?= $channel ?>]"
                                               value="on"
                                            <?= !empty($grid[$eventKey][$channel]) ? 'checked' : '' ?>
                                            <?= $canUpdate ? '' : 'disabled' ?>
                                               aria-label="<?= htmlspecialchars($label . ' via ' . Channel::label($channel)) ?>">
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-actions u-mt-s3">
                <button type="submit" class="btn btn-primary" <?= $canUpdate ? '' : 'disabled' ?>>Save preferences</button>
            </div>
        </form>
    </div>
</div>

<div class="table-card u-maxw-900 u-mt-s4">
    <div class="table-card-head">
        <span class="table-card-title">Devices for app push</span>
    </div>
    <div class="card-body">
        <?php if (empty($devices)): ?>
            <p class="form-text u-m-0">
                No device is registered for your account yet. The mobile app registers itself
                automatically when you sign in with push enabled.
            </p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Token</th>
                        <th class="text-end" width="90">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device): ?>
                        <tr>
                            <td><?= htmlspecialchars(ucfirst((string) $device['platform'])) ?></td>
                            <td class="cell-date"><?= htmlspecialchars(substr((string) $device['token'], 0, 12)) ?>…</td>
                            <td class="table-actions justify-content-end">
                                <form method="POST" action="/notifications/devices/<?= (int) $device['id'] ?>/delete">
                                    <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">
                                    <button type="submit" class="btn btn-sm btn-outline" <?= $canUpdate ? '' : 'disabled' ?>>Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
