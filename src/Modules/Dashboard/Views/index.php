<?php
// Safe defaults to avoid undefined index errors
$userName = htmlspecialchars($_SESSION['user']['first_name'] ?? '');


$totalGatepasses     = (int)($stats['total_gatepasses'] ?? 0);
$pendingApprovals    = (int)($stats['my_pending_approvals'] ?? 0);
$checkedInToday      = (int)($stats['checked_in_today'] ?? 0);
$checkedOutToday     = (int) ($stats['checked_out_today'] ?? 0);
$activeVisitors      = (int)($stats['active_visitors'] ?? 0);
$totalVisitors       = (int)($stats['total_visitors'] ?? 0);
?>

<div class="dashboard-container">

    <h1 class="dashboard-title">
        Welcome, <?= $userName ?>
    </h1>

    <div class="dashboard-grid">

        <?php if (can('gatepass.view')): ?>
            <div class="card">
                <h3>Total Gatepasses</h3>
                <p class="card-value"><?= $totalGatepasses ?></p>
            </div>
        <?php endif; ?>

        <?php if (can('approval.view')): ?>
            <div class="card">
                <h3>My Pending Approvals</h3>
                <p class="card-value"><?= $pendingApprovals ?></p>
            </div>
        <?php endif; ?>

        <?php if (can('gatepass.checkin')): ?>
            <div class="card">
                <h3>Check-ins Today</h3>
                <p class="card-value"><?= $checkedInToday ?></p>
            </div>
        <?php endif; ?>

        <?php if (can('gatepass.checkout')): ?>
            <div class="card">
                <h3>Check-Outs Today</h3>
                <p class="card-value"><?= $checkedOutToday ?></p>
            </div>
        <?php endif; ?>

        <?php if (can('visits.view')): ?>
            <div class="card">
                <h3>Active Visitors</h3>
                <p class="card-value"><?= $activeVisitors ?></p>
            </div>

            <div class="card">
                <h3>Total Visitors</h3>
                <p class="card-value"><?= $totalVisitors ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/charts.php'; ?>


</div>
