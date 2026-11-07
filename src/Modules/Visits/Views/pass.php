<?php
/** @var array $visit @var string $qr */
$onSite    = !empty($visit['checkin_time']) && empty($visit['checkout_time']);
$completed = !empty($visit['checkout_time']);
?>
<div class="login-wrapper">

    <div class="login-brand">
        <div class="brand-name"><?= htmlspecialchars($visit['tenant_name']) ?></div>
    </div>

    <div class="login-card text-center">

        <?php if ($completed): ?>
            <h2>Visit completed</h2>
            <p class="extra-links">This pass is no longer active.</p>
        <?php elseif ($onSite): ?>
            <h2>Visitor on site</h2>
            <p class="extra-links">Checked in at <?= htmlspecialchars(date('Y-m-d H:i', strtotime($visit['checkin_time']))) ?>.</p>
        <?php else: ?>
            <h2>Your visit pass</h2>
            <p class="extra-links">Show this QR code at the gate on arrival.</p>
            <img src="<?= $qr ?>" alt="Visit QR pass" width="220" height="220">
        <?php endif; ?>

        <div class="form-group u-ta-left">
            <table class="u-w-full u-border-collapse">
                <tr>
                    <td><strong>Visitor</strong></td>
                    <td><?= htmlspecialchars($visit['visitor_first'] . ' ' . $visit['visitor_last']) ?></td>
                </tr>
                <tr>
                    <td><strong>Department</strong></td>
                    <td><?= htmlspecialchars($visit['department_name'] ?? '—') ?></td>
                </tr>
                <tr>
                    <td><strong>Host</strong></td>
                    <td><?= htmlspecialchars(trim(($visit['host_first'] ?? '') . ' ' . ($visit['host_last'] ?? '')) ?: '—') ?></td>
                </tr>
                <tr>
                    <td><strong>Purpose</strong></td>
                    <td><?= htmlspecialchars($visit['purpose'] ?? '—') ?></td>
                </tr>
                <tr>
                    <td><strong>Arrival</strong></td>
                    <td><?= !empty($visit['expected_in']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($visit['expected_in']))) : '—' ?></td>
                </tr>
                <tr>
                    <td><strong>Departure</strong></td>
                    <td><?= !empty($visit['expected_out']) ? htmlspecialchars(date('Y-m-d H:i', strtotime($visit['expected_out']))) : '—' ?></td>
                </tr>
                <tr>
                    <td><strong>Status</strong></td>
                    <td><?= htmlspecialchars($visit['visit_status_name'] ?? 'Scheduled') ?></td>
                </tr>
            </table>
        </div>

    </div>

    <p class="login-footer">Digital visitor pass</p>
</div>
