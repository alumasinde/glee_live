<?php

// ── Helpers ──────────────────────────────────────────────────────
function statusLabel(string $code): string {
    return match(strtoupper($code)) {
        'PENDING'      => 'Pending',
        'APPROVED'     => 'Approved',
        'REJECTED'     => 'Rejected',
        'CHECKED_IN'   => 'Checked In',
        'CHECKED_OUT'  => 'Checked Out',
        'CANCELLED'    => 'Cancelled',
        default        => ucwords(strtolower(str_replace('_', ' ', $code))),
    };
}

function statusClass(string $code): string {
    return match(strtoupper($code)) {
        'PENDING'                => 'gs-status--pending',
        'APPROVED'               => 'gs-status--approved',
        'REJECTED', 'CANCELLED'  => 'gs-status--rejected',
        'CHECKED_IN'             => 'gs-status--in',
        'CHECKED_OUT'            => 'gs-status--out',
        default                  => 'gs-status--neutral',
    };
}

$statusCode = strtoupper($gatepass['status_code'] ?? '');
$canCheckin  = (bool)($actions['can_checkin']  ?? false);
$canCheckout = (bool)($actions['can_checkout'] ?? false);
$hasAnyAction = $canCheckin || $canCheckout;
?>

<div class="gs-page" data-gatepass-id="<?= (int) $gatepass['id'] ?>">

    <!-- ── Breadcrumb ───────────────────────────────────────────── -->
    <nav class="gs-breadcrumb" aria-label="Breadcrumb">
        <a href="/gatepasses" class="gs-breadcrumb__link">Gatepasses</a>
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M4.5 2.5l3 3.5-3 3.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
        <span class="gs-breadcrumb__current">GP-<?= (int)$gatepass['id'] ?></span>
    </nav>

    <div class="gs-layout">

        <!-- ── Main column ──────────────────────────────────────── -->
        <div class="gs-main">

            <!-- Gatepass header card -->
            <div class="gs-card gs-card--header">
                <div class="gs-card__row gs-card__row--spread">
                    <div>
                        <div class="gs-ref">GP-<?= (int)$gatepass['id'] ?></div>
                        <h1 class="gs-card__title"><?= htmlspecialchars($visitor['name'] ?? 'Unknown Visitor') ?></h1>
                        <p class="gs-card__sub"><?= htmlspecialchars($visitor['company'] ?? '') ?></p>
                    </div>
                    <span class="gs-status <?= statusClass($statusCode) ?>">
                        <?= statusLabel($statusCode) ?>
                    </span>
                </div>

                <!-- Type + Returnable pills -->
                <div class="gs-pill-row">
                    <span class="gs-pill">
                        <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><rect x="2" y="2" width="9" height="9" rx="2" stroke="currentColor" stroke-width="1.2"/></svg>
                        Type: <?= htmlspecialchars($type['name'] ?? '—') ?>
                    </span>
                    <?php if ((int)($gatepass['is_returnable'] ?? 0) === 1): ?>
                        <span class="gs-pill gs-pill--blue">
                            <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M9.5 4.5A4 4 0 1 0 10 8" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/><path d="M10 4.5H7.5V7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Returnable
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Time details card -->
            <div class="gs-card">
                <h2 class="gs-card__section-title">Time Details</h2>
                <div class="gs-grid-2">
                    <div class="gs-field">
                        <span class="gs-field__label">Scheduled In</span>
                        <span class="gs-field__value"><?= $gatepass['expected_in']  ? date('D, d M Y · H:i', strtotime($gatepass['expected_in']))  : '—' ?></span>
                    </div>
                    <div class="gs-field">
                        <span class="gs-field__label">Scheduled Out</span>
                        <span class="gs-field__value"><?= $gatepass['expected_out'] ? date('D, d M Y · H:i', strtotime($gatepass['expected_out'])) : '—' ?></span>
                    </div>
                    <div class="gs-field">
                        <span class="gs-field__label">Actual Check-in</span>
                        <span class="gs-field__value <?= empty($gatepass['actual_in']) ? 'gs-field__value--muted' : 'gs-field__value--done' ?>">
                            <?= !empty($gatepass['actual_in']) ? date('D, d M Y · H:i', strtotime($gatepass['actual_in'])) : 'Not yet recorded' ?>
                        </span>
                    </div>
                    <div class="gs-field">
                        <span class="gs-field__label">Actual Check-out</span>
                        <span class="gs-field__value <?= empty($gatepass['actual_out']) ? 'gs-field__value--muted' : 'gs-field__value--done' ?>">
                            <?= !empty($gatepass['actual_out']) ? date('D, d M Y · H:i', strtotime($gatepass['actual_out'])) : 'Not yet recorded' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Notes / purpose -->
            <?php if (!empty($gatepass['purpose'])): ?>
                <div class="gs-card">
                    <h2 class="gs-card__section-title">Purpose / Notes</h2>
                    <p class="gs-notes"><?= nl2br(htmlspecialchars($gatepass['purpose'])) ?></p>
                </div>
            <?php endif; ?>

            <!-- Approval history -->
            <?php if (!empty($approvals)): ?>
                <div class="gs-card">
                    <h2 class="gs-card__section-title">Approval History</h2>
                    <ol class="gs-timeline" reversed>
                        <?php foreach ($approvals as $approval): ?>
                            <li class="gs-timeline__item">
                                <span class="gs-timeline__dot gs-timeline__dot--<?= strtolower(htmlspecialchars($approval['decision'] ?? 'pending')) ?>"></span>
                                <div class="gs-timeline__body">
                                    <div class="gs-timeline__top">
                                        <strong><?= htmlspecialchars($approval['approver_name'] ?? 'System') ?></strong>
                                        <span class="gs-timeline__decision gs-timeline__decision--<?= strtolower(htmlspecialchars($approval['decision'] ?? 'pending')) ?>">
                                            <?= htmlspecialchars(ucfirst(strtolower($approval['decision'] ?? 'Pending'))) ?>
                                        </span>
                                    </div>
                                    <time class="gs-timeline__time"><?= date('d M Y, H:i', strtotime($approval['created_at'])) ?></time>
                                    <?php if (!empty($approval['remarks'])): ?>
                                        <p class="gs-timeline__remarks"><?= htmlspecialchars($approval['remarks']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php endif; ?>

        </div><!-- /gs-main -->

        <!-- ── Sidebar: Actions ─────────────────────────────────── -->
        <aside class="gs-sidebar">

            <?php if ($hasAnyAction): ?>
                <div class="gs-card gs-card--actions">
                    <h2 class="gs-card__section-title">Available Actions</h2>
                    <p class="gs-actions__hint">
                        Actions shown are permitted by the
                        <strong><?= htmlspecialchars($type['name'] ?? 'this') ?></strong> type
                        and eligible based on current workflow state.
                    </p>

                    <div class="gs-action-group">

                        <?php if ($canCheckin): ?>
                            <button
                                class="gs-action-btn gs-action-btn--checkin"
                                data-gp-action="checkin"
                            >
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 3v7.5M9 10.5l-3-3M9 10.5l3-3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 15h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                Record Check-in
                            </button>
                        <?php endif; ?>

                        <?php if ($canCheckout): ?>
                            <button
                                class="gs-action-btn gs-action-btn--checkout"
                                data-gp-action="checkout"
                            >
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none"><path d="M9 15V7.5M9 7.5l-3 3M9 7.5l3 3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 3h10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                                Record Check-out
                            </button>
                        <?php endif; ?>

                    </div>
                </div>
            <?php else: ?>
                <!-- No actions available - explain why clearly -->
                <div class="gs-card gs-card--no-actions">
                    <div class="gs-no-actions">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none"><circle cx="16" cy="16" r="13" stroke="currentColor" stroke-width="1.5"/><path d="M16 10v6M16 20h.01" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
                        <p class="gs-no-actions__title">No actions available</p>
                        <p class="gs-no-actions__reason">
                            <?php
                            // Explain clearly which gate is blocking action
                            $typeAllowed = json_decode($type['allowed_actions'] ?? '{}', true) ?: [];
                            $typeAllowsCheckin  = (bool)($typeAllowed['checkin']  ?? false);
                            $typeAllowsCheckout = (bool)($typeAllowed['checkout'] ?? false);

                            if (!$typeAllowsCheckin && !$typeAllowsCheckout):
                            ?>
                                Neither check-in nor check-out is enabled for the
                                <strong><?= htmlspecialchars($type['name'] ?? 'this') ?></strong> type.
                                Update the type configuration in Settings to enable actions.
                            <?php elseif (in_array($statusCode, ['REJECTED', 'CANCELLED'])): ?>
                                This gatepass has been <strong><?= statusLabel($statusCode) ?></strong>
                                and no further actions are permitted.
                            <?php elseif ($statusCode === 'PENDING'): ?>
                                This gatepass is <strong>pending approval</strong> and cannot be acted on yet.
                            <?php else: ?>
                                All permitted actions for this gatepass have already been completed
                                based on its current status.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Meta info card -->
            <div class="gs-card">
                <h2 class="gs-card__section-title">Meta</h2>
                <dl class="gs-meta">
                    <div class="gs-meta__row">
                        <dt>Created</dt>
                        <dd><?= date('d M Y, H:i', strtotime($gatepass['created_at'])) ?></dd>
                    </div>
                    <div class="gs-meta__row">
                        <dt>Last Updated</dt>
                        <dd><?= date('d M Y, H:i', strtotime($gatepass['updated_at'] ?? $gatepass['created_at'])) ?></dd>
                    </div>
                    <div class="gs-meta__row">
                        <dt>Created By</dt>
                        <dd><?= htmlspecialchars($gatepass['created_by_name'] ?? '—') ?></dd>
                    </div>
                </dl>
            </div>

        </aside><!-- /gs-sidebar -->

    </div><!-- /gs-layout -->
</div>

<!-- ── Confirm Action Modal ──────────────────────────────────────── -->
<div class="gs-overlay" id="gsOverlay" role="dialog" aria-modal="true" aria-labelledby="gsModalTitle" hidden>
    <div class="gs-modal">
        <div class="gs-modal__icon" id="gsModalIcon"></div>
        <h2 class="gs-modal__title" id="gsModalTitle"></h2>
        <p class="gs-modal__body" id="gsModalBody"></p>
        <div class="gs-modal__footer">
            <button type="button" class="gt-btn gt-btn--ghost" id="gsCancelBtn">Cancel</button>
            <button type="button" class="gt-btn" id="gsConfirmBtn">Confirm</button>
        </div>
    </div>
</div>


<!-- ── Script ────────────────────────────────────────────────────── -->
<script src="/assets/js/pages/gatepass-show.js" defer></script>
