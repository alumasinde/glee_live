<?php
/**
 * Self-service approval delegation (out-of-office coverage).
 *
 * @var list<array> $outgoing
 * @var list<array> $incoming
 * @var list<array> $candidates
 * @var list<array> $roles
 */

use App\Middleware\CSRFMiddleware;

$outgoing   = $outgoing ?? [];
$incoming   = $incoming ?? [];
$candidates = $candidates ?? [];
$roles      = $roles ?? [];

$toLocal = static function (?string $ts): string {
    $time = $ts === null ? null : strtotime($ts);

    return $time ? date('Y-m-d\TH:i', $time) : '';
};
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-people-arrows"></i> <?= htmlspecialchars($title ?? 'Approval Delegation') ?>
    </h1>
    <div class="page-actions">
        <a class="btn btn-sm btn-secondary" href="/approvals">
            <i class="fa-solid fa-stamp"></i> My approvals
        </a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="gp-alert gp-alert--danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<p class="form-text" style="margin-top:0;max-width:900px;">
    While a delegation is live, <em>new</em> approval requests that reach you are routed to your
    delegate as well. Requests already sitting in your queue stay yours — use the approval screen
    or ask an administrator to reassign those.
</p>

<div class="table-card" style="max-width:900px;">
    <div class="table-card-head">
        <span class="table-card-title">My out-of-office cover</span>
    </div>
    <div class="card-body">
        <?php if (!$candidates): ?>
            <p class="form-text" style="margin:0;">
                There are no other active users in this company to delegate to yet.
            </p>
        <?php else: ?>
            <form method="POST" action="/approvals/delegation">
                <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">

                <div class="filter-grid">
                    <div class="form-group">
                        <label for="delegate_user_id">Cover me for</label>
                        <select class="form-select" id="delegate_user_id" name="delegate_user_id" required>
                            <option value="">Choose a colleague…</option>
                            <?php foreach ($candidates as $candidate): ?>
                                <option value="<?= (int) $candidate['id'] ?>">
                                    <?= htmlspecialchars($candidate['name']) ?>
                                    <?= !empty($candidate['roles']) ? ' — ' . htmlspecialchars($candidate['roles']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($roles): ?>
                        <div class="form-group">
                            <label for="role_id">Scope</label>
                            <select class="form-select" id="role_id" name="role_id">
                                <option value="">Every approval I handle</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= (int) $role['id'] ?>">
                                        Only <?= htmlspecialchars($role['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="starts_at">From</label>
                        <input class="form-control" type="datetime-local" id="starts_at" name="starts_at"
                               value="<?= $toLocal(date('Y-m-d H:i:s')) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="ends_at">Until</label>
                        <input class="form-control" type="datetime-local" id="ends_at" name="ends_at"
                               value="<?= $toLocal(date('Y-m-d H:i:s', strtotime('+7 days'))) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="note">Note <span class="cell-date">(optional)</span></label>
                        <input class="form-control" type="text" id="note" name="note" maxlength="180"
                               placeholder="Annual leave, sick cover…">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Add delegation</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="table-card" style="max-width:900px;margin-top:var(--space-4);">
    <div class="table-card-head">
        <span class="table-card-title">Scheduled</span>
    </div>

    <?php if (!$outgoing): ?>
        <div class="empty-state">
            <i class="fa-solid fa-calendar-check"></i>
            <div class="empty-state-title">No cover set</div>
            <div class="empty-state-text">Approvals come to you as usual.</div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Delegate</th>
                        <th>Scope</th>
                        <th>Window</th>
                        <th>Status</th>
                        <th class="text-end" width="90">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outgoing as $row): ?>
                        <?php $live = !empty($row['has_expired']) ? 'Expired' : (strtotime($row['starts_at']) > time() ? 'Scheduled' : 'Live'); ?>
                        <tr>
                            <td>
                                <div class="cell-main"><?= htmlspecialchars($row['delegate_name']) ?></div>
                                <div class="cell-date"><?= htmlspecialchars($row['delegate_email'] ?? '') ?></div>
                                <?php if (!empty($row['note'])): ?>
                                    <div class="cell-date"><?= htmlspecialchars($row['note']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['role_name'] ?? 'All approvals') ?></td>
                            <td class="cell-date">
                                <?= format_date($row['starts_at'] ?? null) ?> → <?= format_date($row['ends_at'] ?? null) ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $live === 'Live' ? 'success' : ($live === 'Expired' ? 'secondary' : 'info') ?>">
                                    <?= $live ?>
                                </span>
                            </td>
                            <td class="table-actions justify-content-end">
                                <form method="POST" action="/approvals/delegation/<?= (int) $row['id'] ?>/delete">
                                    <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">
                                    <button type="submit" class="btn btn-sm btn-outline">Cancel</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if ($incoming): ?>
    <div class="table-card" style="max-width:900px;margin-top:var(--space-4);">
        <div class="table-card-head">
            <span class="table-card-title">I am covering</span>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>For</th>
                        <th>Scope</th>
                        <th>Until</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incoming as $row): ?>
                        <tr>
                            <td class="cell-main"><?= htmlspecialchars($row['delegator_name']) ?></td>
                            <td><?= htmlspecialchars($row['role_name'] ?? 'All approvals') ?></td>
                            <td class="cell-date"><?= format_date($row['ends_at'] ?? null) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
