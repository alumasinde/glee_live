<div class="page-header mb-3">
    <h2>
        <?= htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']) ?>
    </h2>
</div>

<div class="card mb-4">
    <div class="card-body">
        <p><strong>ID Type:</strong> <?= htmlspecialchars($visitor['id_type_name'] ?? '-') ?></p>
        <p><strong>ID Number:</strong> <?= htmlspecialchars($visitor['id_number'] ?? '-') ?></p>
        <p><strong>Company:</strong> <?= htmlspecialchars($visitor['company_name'] ?? '-') ?></p>
        <p><strong>Risk Score:</strong> <?= (int)$visitor['risk_score'] ?></p>
        <p>
            <strong>Status:</strong>
            <?php if ((int)$visitor['is_blacklisted'] === 1): ?>
                <span class="badge bg-danger">Blacklisted</span>
            <?php else: ?>
                <span class="badge bg-success">Clear</span>
            <?php endif; ?>
        </p>
    </div>
</div>

<h4>Visit History</h4>

<table class="table table-bordered table-sm">
    <thead class="table-light">
        <tr>
            <th>ID</th>
            <th>Department</th>
            <th>Status</th>
            <th>Check In</th>
            <th>Check Out</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!empty($visitor['visits'])): ?>
            <?php foreach ($visitor['visits'] as $visit): ?>
                <tr>
                    <td>#<?= $visit['id'] ?></td>
                    <td><?= htmlspecialchars($visit['department_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($visit['status_name'] ?? '-') ?></td>
                    <td><?= $visit['checkin_time'] ?? '-' ?></td>
                    <td><?= $visit['checkout_time'] ?? '-' ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" class="text-muted text-center">
                    No visits recorded.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>