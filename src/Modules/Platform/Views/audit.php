<h1 class="page-heading">Platform audit</h1>

<p class="platform-meta">Provider-side actions only; tenant audit stays inside each tenant.</p>

<?php if (empty($logs)): ?>
    <div class="empty-state">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <div class="empty-state-title">No platform activity yet</div>
        <div class="empty-state-text">Sign-ins and policy changes will appear here.</div>
    </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Subject</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $log['created_at']) ?></td>
                    <td>
                        <?= htmlspecialchars((string) ($log['platform_user_name'] ?? 'System')) ?>
                        <?php if (!empty($log['platform_user_email'])): ?>
                            <span class="cell-sub"><?= htmlspecialchars((string) $log['platform_user_email']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars((string) $log['action']) ?></code></td>
                    <td>
                        <?= htmlspecialchars((string) ($log['subject_type'] ?? '—')) ?>
                        <?php if (!empty($log['subject_id'])): ?>
                            #<?= (int) $log['subject_id'] ?>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars((string) ($log['ip_address'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
