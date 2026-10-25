<?php
/**
 * Per-visit badge issuance screen (POSTs to the existing /badges/{visit}/issue route).
 *
 * @var array       $visit   Visit + holder snapshot from BadgeRepository::visitForIssuance()
 * @var array       $visitor Visitor record (risk / blacklist)
 * @var string|null $error   Service error (flash-rendered by the layout otherwise)
 */

$e = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$visitId    = (int) ($visit['id'] ?? 0);
$isCheckedIn = !empty($visit['checkin_time']);
$isCheckedOut = !empty($visit['checkout_time']);
$badgeOut   = !empty($visit['active_badge_code']);
$blacklisted = (int) ($visitor['is_blacklisted'] ?? 0) === 1;
$risk       = (int) ($visitor['risk_score'] ?? 0);

$blocked = !$isCheckedIn || $isCheckedOut || $badgeOut || $blacklisted;
$reason  = match (true) {
    !$isCheckedIn   => 'The visitor is not checked in yet.',
    $isCheckedOut   => 'The visit already closed out.',
    $badgeOut      => 'A badge is already out on this visit — return it first.',
    $blacklisted   => 'Blacklisted visitors cannot receive badges.',
    default         => '',
};
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-id-badge"></i> Issue Visitor Badge
    </h1>

    <div class="page-actions">
        <a href="/badges" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Badges
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= $e($error) ?></div>
<?php endif; ?>

<div class="table-card">
    <div class="table-card-head">
        <span class="table-card-title">
            <i class="fa-solid fa-user-check"></i> Visit #<?= $visitId ?>
        </span>
    </div>

    <div class="card-body">
        <div class="info-grid">
            <div class="info-item">
                <label>Holder</label>
                <span><?= $e($visit['visitor_name'] ?? '') ?></span>
            </div>

            <div class="info-item">
                <label>ID Number</label>
                <span class="cell-mono"><?= $e($visit['id_number'] ?? '—') ?></span>
            </div>

            <div class="info-item">
                <label>Company</label>
                <span><?= $e($visit['company_name'] ?? '—') ?></span>
            </div>

            <div class="info-item">
                <label>Department</label>
                <span><?= $e($visit['department_name'] ?? '—') ?></span>
            </div>

            <div class="info-item">
                <label>Host</label>
                <span><?= $e($visit['host_name'] ?? '—') ?></span>
            </div>

            <div class="info-item">
                <label>Purpose</label>
                <span><?= $e($visit['purpose'] ?? '—') ?></span>
            </div>

            <div class="info-item">
                <label>Check-In</label>
                <span><?= format_date($visit['checkin_time'] ?? null) ?></span>
            </div>

            <div class="info-item">
                <label>Gatepass</label>
                <span><?= $e($visit['gatepass_number'] ?? '—') ?></span>
            </div>

            <div class="info-item">
                <label>Risk</label>
                <span class="badge <?= $risk >= 70 ? 'badge-danger' : ($risk >= 40 ? 'badge-warning' : 'badge-success') ?>">
                    <?= $risk ?>
                </span>
            </div>

            <div class="info-item">
                <label>Badge Out</label>
                <span>
                    <?php if ($badgeOut): ?>
                        <span class="badge badge-warning"><?= $e($visit['active_badge_code']) ?></span>
                    <?php else: ?>
                        <span class="badge badge-secondary">None</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php if ($blocked): ?>
            <div class="alert alert-warning" style="margin-top:var(--space-4)">
                <i class="fa-solid fa-circle-exclamation"></i> <?= $e($reason) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$blocked): ?>
    <div class="table-card">
        <div class="table-card-head">
            <span class="table-card-title">
                <i class="fa-solid fa-print"></i> Badge Code
            </span>
        </div>

        <div class="card-body">
            <form method="POST" action="/badges/<?= $visitId ?>/issue">
                <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                <input type="hidden" name="redirect_to" value="/badges">

                <div class="form-group">
                    <label class="form-label" for="badge_code">Badge Code <span class="text-muted">(optional)</span></label>
                    <input
                        class="form-control"
                        type="text"
                        name="badge_code"
                        id="badge_code"
                        value="<?= $e($_POST['badge_code'] ?? '') ?>"
                        placeholder="Auto-generated when left blank"
                        pattern="[A-Za-z0-9][A-Za-z0-9_\-]{2,59}"
                        autocomplete="off"
                    >
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-id-badge"></i> Issue Badge
                    </button>
                    <a href="/badges/create" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="table-card">
        <div class="card-body">
            <?php if ($badgeOut): ?>
                <form method="POST" action="/badges/<?= $visitId ?>/return" class="form-actions"
                      onsubmit="return confirm('Record this badge as returned?')">
                    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                    <input type="hidden" name="redirect_to" value="/badges">
                    <button class="btn btn-secondary">
                        <i class="fa-solid fa-rotate-left"></i> Return Badge
                    </button>
                </form>
            <?php else: ?>
                <div class="form-actions">
                    <a href="/badges/create" class="btn btn-secondary">
                        <i class="fa-solid fa-id-badge"></i> Choose Another Visit
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
