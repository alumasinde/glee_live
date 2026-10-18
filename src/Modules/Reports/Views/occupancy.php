<?php
    /** @var array $data */
    $gates      = $data['gates'];
    $gatepasses = $data['gatepasses'];
    $visits     = $data['visits'];
    $recent     = $data['recent_activity'];

    $totalOnSite = count($gatepasses) + count($visits);
?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-warehouse"></i> Occupancy
    </h1>

    <div class="page-actions">
        <a href="/reports" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
    </div>
</div>

<div class="report-dashboard" style="margin-bottom: var(--space-6);">
    <div class="report-card">
        <div class="card-icon"><i class="fa-solid fa-users-rays"></i></div>
        <h3><?= (int) $totalOnSite ?></h3>
        <p>People &amp; items currently on site</p>
    </div>
    <div class="report-card">
        <div class="card-icon"><i class="fa-solid fa-file-signature"></i></div>
        <h3><?= count($gatepasses) ?></h3>
        <p>Gatepasses checked in, not out</p>
    </div>
    <div class="report-card">
        <div class="card-icon"><i class="fa-solid fa-user"></i></div>
        <h3><?= count($visits) ?></h3>
        <p>Visitors checked in, not out</p>
    </div>
</div>

<!-- ===================== PER GATE ===================== -->
<div class="table-card mb-4">
    <div class="table-card-head">
        <span class="table-card-title"><i class="fa-solid fa-door-open"></i> Gates</span>
        <span class="results-count"><strong><?= count($gates) ?></strong> gate<?= count($gates) === 1 ? '' : 's' ?></span>
    </div>

    <?php if (!empty($gates)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gate</th>
                        <th>Code</th>
                        <th>On site (last in)</th>
                        <th>Scans today</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gates as $gate): ?>
                        <tr>
                            <td><span class="cell-main"><?= htmlspecialchars($gate['name']) ?></span></td>
                            <td><span class="cell-mono"><?= htmlspecialchars($gate['code']) ?></span></td>
                            <td><strong><?= (int) $gate['on_site'] ?></strong></td>
                            <td><?= (int) $gate['scans_today'] ?></td>
                            <td>
                                <?php if ((int) $gate['is_active'] === 1): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-door-open"></i>
            <div class="empty-state-title">No gates configured</div>
            <div class="empty-state-text">Add gates under Settings → Gates to see per-gate occupancy.</div>
        </div>
    <?php endif; ?>
</div>

<!-- ===================== ITEMS ON SITE ===================== -->
<div class="table-card mb-4">
    <div class="table-card-head">
        <span class="table-card-title"><i class="fa-solid fa-box"></i> Gatepasses on site</span>
        <span class="results-count"><strong><?= count($gatepasses) ?></strong> item<?= count($gatepasses) === 1 ? '' : 's' ?></span>
    </div>

    <?php if (!empty($gatepasses)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Number</th>
                        <th>Holder</th>
                        <th>Department</th>
                        <th>Purpose</th>
                        <th>Checked in</th>
                        <th>Gate in</th>
                        <th>Returnable</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gatepasses as $gp): ?>
                        <tr>
                            <td><span class="cell-mono"><?= htmlspecialchars($gp['gatepass_number']) ?></span></td>
                            <td><?= htmlspecialchars(trim((string) $gp['holder_name']) ?: '—') ?></td>
                            <td><?= htmlspecialchars($gp['department_name'] ?? '—') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($gp['purpose'] ?? '—') ?></td>
                            <td class="cell-date"><?= format_date($gp['actual_in'] ?? null) ?></td>
                            <td><?= htmlspecialchars($gp['gate_name'] ?? 'Unassigned') ?></td>
                            <td>
                                <?php if ((int) $gp['is_returnable'] === 1): ?>
                                    <span class="badge badge-info">Yes</span>
                                <?php else: ?>
                                    <span class="text-muted">No</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-box"></i>
            <div class="empty-state-title">Nothing on site</div>
            <div class="empty-state-text">No gatepasses are currently checked in.</div>
        </div>
    <?php endif; ?>
</div>

<!-- ===================== VISITORS ON SITE ===================== -->
<div class="table-card mb-4">
    <div class="table-card-head">
        <span class="table-card-title"><i class="fa-solid fa-person"></i> Visitors on site</span>
        <span class="results-count"><strong><?= count($visits) ?></strong> visitor<?= count($visits) === 1 ? '' : 's' ?></span>
    </div>

    <?php if (!empty($visits)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Visitor</th>
                        <th>Department</th>
                        <th>Host</th>
                        <th>Purpose</th>
                        <th>Arrived</th>
                        <th>Gate in</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visits as $visit): ?>
                        <tr>
                            <td><span class="cell-main"><?= htmlspecialchars($visit['visitor_name']) ?></span></td>
                            <td><?= htmlspecialchars($visit['department_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars(trim((string) $visit['host_name']) ?: '—') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($visit['purpose'] ?? '—') ?></td>
                            <td class="cell-date"><?= format_date($visit['checkin_time'] ?? null) ?></td>
                            <td><?= htmlspecialchars($visit['gate_name'] ?? 'Unassigned') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-person"></i>
            <div class="empty-state-title">No visitors on site</div>
            <div class="empty-state-text">Checked-in visitors who have not left yet appear here.</div>
        </div>
    <?php endif; ?>
</div>

<!-- ===================== RECENT SCANS ===================== -->
<div class="table-card">
    <div class="table-card-head">
        <span class="table-card-title"><i class="fa-solid fa-qrcode"></i> Recent gate activity</span>
    </div>

    <?php if (!empty($recent)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Gate</th>
                        <th>Subject</th>
                        <th>Action</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $scan): ?>
                        <tr>
                            <td class="cell-date"><?= format_date($scan['created_at'] ?? null) ?></td>
                            <td><?= htmlspecialchars($scan['gate_name'] ?? '—') ?></td>
                            <td>
                                <?= $scan['subject_type'] === 'visit'
                                    ? htmlspecialchars($scan['visitor_name'] ?: 'Visit')
                                    : htmlspecialchars($scan['gatepass_number'] ?: ('#' . (int) ($scan['subject_id'] ?? 0))) ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars((string) $scan['action']) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', (string) $scan['action'])) ?>
                                </span>
                            </td>
                            <td class="text-muted"><?= htmlspecialchars($scan['source']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fa-solid fa-qrcode"></i>
            <div class="empty-state-title">No scans yet</div>
            <div class="empty-state-text">Gate activity will appear here as passes are scanned.</div>
        </div>
    <?php endif; ?>
</div>
