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
        <a href="/reports" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Reports
        </a>
    </div>
</div>

<div class="report-dashboard" style="margin-bottom: 20px;">
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
<div class="table-card">
    <h3 style="margin-bottom: 15px;">Gates</h3>

    <?php if (!empty($gates)): ?>
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
                        <td><?= htmlspecialchars($gate['name']) ?></td>
                        <td><?= htmlspecialchars($gate['code']) ?></td>
                        <td><strong><?= (int) $gate['on_site'] ?></strong></td>
                        <td><?= (int) $gate['scans_today'] ?></td>
                        <td><?= (int) $gate['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No gates configured. Add gates under Settings → Gates to see per-gate occupancy.</p>
    <?php endif; ?>
</div>

<!-- ===================== ITEMS ON SITE ===================== -->
<div class="table-card">
    <h3 style="margin-bottom: 15px;">Gatepasses on site</h3>

    <?php if (!empty($gatepasses)): ?>
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
                        <td><?= htmlspecialchars($gp['gatepass_number']) ?></td>
                        <td><?= htmlspecialchars(trim((string) $gp['holder_name']) ?: '-') ?></td>
                        <td><?= htmlspecialchars($gp['department_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($gp['purpose'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($gp['actual_in'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($gp['gate_name'] ?? 'Unassigned') ?></td>
                        <td><?= (int) $gp['is_returnable'] === 1 ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No gatepasses currently on site.</p>
    <?php endif; ?>
</div>

<!-- ===================== VISITORS ON SITE ===================== -->
<div class="table-card">
    <h3 style="margin-bottom: 15px;">Visitors on site</h3>

    <?php if (!empty($visits)): ?>
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
                        <td><?= htmlspecialchars($visit['visitor_name']) ?></td>
                        <td><?= htmlspecialchars($visit['department_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars(trim((string) $visit['host_name']) ?: '-') ?></td>
                        <td><?= htmlspecialchars($visit['purpose'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($visit['checkin_time'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($visit['gate_name'] ?? 'Unassigned') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No visitors currently on site.</p>
    <?php endif; ?>
</div>

<!-- ===================== RECENT SCANS ===================== -->
<div class="table-card">
    <h3 style="margin-bottom: 15px;">Recent gate activity</h3>

    <?php if (!empty($recent)): ?>
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
                        <td><?= htmlspecialchars($scan['created_at']) ?></td>
                        <td><?= htmlspecialchars($scan['gate_name'] ?? '-') ?></td>
                        <td>
                            <?= $scan['subject_type'] === 'visit'
                                ? htmlspecialchars($scan['visitor_name'] ?: 'Visit')
                                : htmlspecialchars($scan['gatepass_number'] ?: ('#' . (int) ($scan['subject_id'] ?? 0))) ?>
                        </td>
                        <td><?= htmlspecialchars(str_replace('_', ' ', (string) $scan['action'])) ?></td>
                        <td><?= htmlspecialchars($scan['source']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No scans recorded yet.</p>
    <?php endif; ?>
</div>
