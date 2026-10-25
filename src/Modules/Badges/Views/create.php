<?php
/**
 * Badge issuance form.
 *
 * @var array       $candidates Visits eligible for a badge (checked in, not checked out, no live badge)
 * @var array|null  $visit      Pre-selected visit snapshot (from ?visit_id=) or null
 * @var string      $badgeCode  Previously submitted badge code (old-input)
 * @var int         $visitId    Previously submitted visit id (old-input)
 * @var string|null $error      Validation / service error
 */

$candidates = $candidates ?? [];
$visit      = $visit ?? null;
$badgeCode  = (string) ($badgeCode ?? ($_POST['badge_code'] ?? ''));
$visitId    = (int) ($visitId ?? ($_POST['visit_id'] ?? 0));
$selectedId = $visit ? (int) $visit['id'] : $visitId;

$e = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
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

<?php if (empty($candidates) && !$visit): ?>
    <div class="table-card">
        <div class="empty-state">
            <i class="fa-solid fa-id-badge"></i>
            <div class="empty-state-title">No visit is waiting for a badge</div>
            <div class="empty-state-text">
                A badge can only be issued while the visitor is checked in and has not checked out.
            </div>
            <?php if (can('visits.view')): ?>
                <a href="/visits" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-person-walking-arrow-right"></i> View Active Visits
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>

    <?php if ($visit): ?>
        <div class="table-card">
            <div class="table-card-head">
                <span class="table-card-title">
                    <i class="fa-solid fa-user-check"></i> Selected Visit
                </span>
            </div>

            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Holder</label>
                        <span><?= $e($visit['visitor_name']) ?></span>
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
                        <label>Check-In</label>
                        <span><?= format_date($visit['checkin_time'] ?? null) ?></span>
                    </div>

                    <div class="info-item">
                        <label>Gatepass</label>
                        <span><?= $e($visit['gatepass_number'] ?? '—') ?></span>
                    </div>

                    <div class="info-item">
                        <label>Badge Out</label>
                        <span>
                            <?php if (!empty($visit['active_badge_code'])): ?>
                                <span class="badge badge-warning"><?= $e($visit['active_badge_code']) ?></span>
                            <?php else: ?>
                                <span class="badge badge-secondary">None</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="table-card">
        <div class="table-card-head">
            <span class="table-card-title">
                <i class="fa-solid fa-print"></i> Issuance
            </span>
        </div>

        <div class="card-body">
            <form method="POST" action="/badges">
                <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

                <div class="form-group">
                    <label class="form-label" for="visit_id">Visit <span class="text-muted">(checked in, no badge out)</span></label>

                    <select class="form-control" name="visit_id" id="visit_id" <?= $visit ? 'readonly' : 'required' ?>>
                        <option value="">&mdash; Select Visit &mdash;</option>

                        <?php
                            // Keep the chosen visit selectable even if the controller
                            // handed it to us outside the candidate list.
                            $listing = $candidates;

                            if ($visit && !array_filter(
                                $listing,
                                static fn (array $c): bool => (int) $c['id'] === (int) $visit['id']
                            )) {
                                array_unshift($listing, $visit);
                            }
                        ?>

                        <?php foreach ($listing as $candidate): ?>
                            <?php $cid = (int) $candidate['id']; ?>
                            <option value="<?= $cid ?>" <?= $selectedId === $cid ? 'selected' : '' ?>>
                                <?= $e($candidate['visitor_name']) ?>
                                · #<?= $cid ?>
                                <?= !empty($candidate['checkin_time']) ? ' · in ' . format_date($candidate['checkin_time']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="badge_code">Badge Code <span class="text-muted">(optional)</span></label>
                    <input
                        class="form-control"
                        type="text"
                        name="badge_code"
                        id="badge_code"
                        value="<?= $e($badgeCode) ?>"
                        placeholder="Leave blank to auto-generate (BDG-XXXXXXXX)"
                        pattern="[A-Za-z0-9][A-Za-z0-9_\-]{2,59}"
                        autocomplete="off"
                    >
                    <small class="form-text text-muted">
                        Use the number printed on the physical card, or leave it empty and the system generates one.
                    </small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-id-badge"></i> Issue Badge
                    </button>

                    <?php if ($visit): ?>
                        <a href="/badges/<?= (int) $visit['id'] ?>/issue" class="btn btn-secondary">
                            <i class="fa-solid fa-up-right-from-square"></i> Open Per-Visit Screen
                        </a>
                    <?php endif; ?>

                    <a href="/badges" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>
