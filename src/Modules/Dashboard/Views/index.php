<?php
// Safe defaults to avoid undefined index errors
$userName = htmlspecialchars($_SESSION['user']['first_name'] ?? '');


$totalGatepasses     = (int)($stats['total_gatepasses'] ?? 0);
$pendingApprovals    = (int)($stats['my_pending_approvals'] ?? 0);
$checkedInToday      = (int)($stats['checked_in_today'] ?? 0);
$checkedOutToday     = (int) ($stats['checked_out_today'] ?? 0);
$activeVisitors      = (int)($stats['active_visitors'] ?? 0);
$totalVisitors       = (int)($stats['total_visitors'] ?? 0);
$peopleOnSite        = (int)($stats['people_on_site'] ?? 0);
$itemsOnSite         = (int)($stats['items_on_site'] ?? 0);
$scansToday          = (int)($stats['scans_today'] ?? 0);
$blacklisted         = (int)($stats['blacklisted_visitors'] ?? 0);
$recentScans         = $stats['recent_scans'] ?? [];
$topDepartments      = $stats['top_departments'] ?? [];
?>

<div class="dashboard-container">

    <div class="page-header">
        <h1 class="dashboard-title">
            Welcome, <?= $userName ?>
        </h1>

        <div class="page-actions">
            <?php if (can('gatepass.create')): ?>
                <a href="/gatepasses/create" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> New Gatepass
                </a>
            <?php endif; ?>

            <?php if (can('visits.create')): ?>
                <a href="/visits/create" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-user-plus"></i> New Visit
                </a>
            <?php endif; ?>

            <?php if (can('gatepass.scan')): ?>
                <a href="/gatepasses/scan" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-qrcode"></i> Scan
                </a>
            <?php endif; ?>
        </div>
    </div>

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

        <?php if (can('reports.view_occupancy')): ?>
            <div class="card">
                <h3>People on Site</h3>
                <p class="card-value"><?= $peopleOnSite ?></p>
            </div>

            <div class="card">
                <h3>Items on Site</h3>
                <p class="card-value"><?= $itemsOnSite ?></p>
            </div>
        <?php endif; ?>

        <?php if (can('reports.view_scans')): ?>
            <div class="card">
                <h3>Scans Today</h3>
                <p class="card-value"><?= $scansToday ?></p>
            </div>
        <?php endif; ?>

        <?php if (can('visitors.blacklist')): ?>
            <div class="card">
                <h3>Blacklisted Visitors</h3>
                <p class="card-value"><?= $blacklisted ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php require __DIR__ . '/charts.php'; ?>

    <?php if (can('reports.view_scans') || can('reports.view_departments')): ?>
        <div class="dashboard-grid u-items-start">

            <?php if (can('reports.view_scans')): ?>
                <div class="table-card">
                    <div class="table-card-head">
                        <span class="table-card-title">
                            <i class="fa-solid fa-door-open"></i> Recent Gate Activity
                        </span>
                    </div>

                    <?php if (empty($recentScans)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-satellite-dish"></i>
                            <div class="empty-state-title">No scans yet</div>
                            <div class="empty-state-text">Gate scans will appear here as they happen.</div>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <tbody>
                                <?php foreach ($recentScans as $scan): ?>
                                    <tr>
                                        <td class="cell-date"><?= format_date($scan['created_at']) ?></td>
                                        <td><?= htmlspecialchars($scan['gate_name'] ?? 'Unassigned') ?></td>
                                        <td>
                                            <span class="cell-main">
                                                <?= $scan['subject_type'] === 'visit'
                                                    ? htmlspecialchars($scan['visitor_name'] ?: 'Visit')
                                                    : htmlspecialchars($scan['gatepass_number'] ?: ('#' . (int) ($scan['subject_id'] ?? 0))) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= htmlspecialchars($scan['action']) ?>">
                                                <?= htmlspecialchars(str_replace('_', ' ', (string) $scan['action'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="table-card-foot">
                            <a href="/reports/scan-activity" class="back-link">
                                View all scans <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (can('reports.view_departments')): ?>
                <div class="table-card">
                    <div class="table-card-head">
                        <span class="table-card-title">
                            <i class="fa-solid fa-building"></i> Top Departments This Month
                        </span>
                    </div>

                    <?php if (empty($topDepartments)): ?>
                        <div class="empty-state">
                            <i class="fa-solid fa-building"></i>
                            <div class="empty-state-title">No department activity</div>
                            <div class="empty-state-text">Departments with gatepasses or visits this month will rank here.</div>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Department</th>
                                    <th>Gatepasses</th>
                                    <th>Visits</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topDepartments as $dept): ?>
                                    <tr>
                                        <td><span class="cell-main"><?= htmlspecialchars($dept['name']) ?></span></td>
                                        <td><?= (int) $dept['gatepasses'] ?></td>
                                        <td><?= (int) $dept['visits'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="table-card-foot">
                            <a href="/reports/departments" class="back-link">
                                Full department report <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>

</div>
