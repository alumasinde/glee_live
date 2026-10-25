<?php
/** @var array $workflow */
/** @var array $steps */
/** @var array $roles */
/** @var array $departments */

$token = \App\Middleware\CSRFMiddleware::token();

/** Fieldset shared by the add form and each inline edit form. */
function workflow_step_fields(array $s, array $roles, array $departments, string $idPrefix): void
{
    $val = fn(string $key, $default = '') => htmlspecialchars((string) ($s[$key] ?? $default));
    $sel = fn(string $key, string $option, $default) => ((string) ($s[$key] ?? $default) === $option) ? 'selected' : '';
    $deptId = (int) ($s['department_id'] ?? 0);
?>
    <div class="form-grid">
        <div class="form-group">
            <label>Step Order *</label>
            <input type="number" name="step_order" class="form-control" min="1" required
                   value="<?= $val('step_order') ?>">
        </div>

        <div class="form-group">
            <label>Step Name *</label>
            <input type="text" name="step_name" class="form-control" placeholder="e.g. HOD Approval" required
                   value="<?= $val('name') ?>">
        </div>

        <div class="form-group">
            <label>Assign Role *</label>
            <select name="role_id" class="form-control" required>
                <option value="">Select Role</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>"
                        <?= (int) ($s['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Fallback Role (optional)</label>
            <select name="fallback_role_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>"
                        <?= (int) ($s['fallback_role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Used only when no eligible approver exists in the primary role.</small>
        </div>

        <div class="form-group">
            <label>Approval Mode</label>
            <select name="approval_mode" class="form-control">
                <option value="any" <?= $sel('approval_mode', 'any', 'any') ?>>Any approver (first responder)</option>
                <option value="all" <?= $sel('approval_mode', 'all', 'any') ?>>All approvers (unanimous)</option>
            </select>
        </div>

        <div class="form-group">
            <label>Approver Scope</label>
            <select name="department_source" class="form-control js-dept-source" data-target="<?= $idPrefix ?>-dept">
                <option value="none" <?= $sel('department_source', 'none', 'none') ?>>Whole tenant</option>
                <option value="gatepass" <?= $sel('department_source', 'gatepass', 'none') ?>>Requester's department</option>
                <option value="step" <?= $sel('department_source', 'step', 'none') ?>>Fixed department</option>
            </select>
        </div>

        <div class="form-group">
            <label>Department (fixed scope only)</label>
            <select id="<?= $idPrefix ?>-dept" name="department_id" class="form-control">
                <option value="">—</option>
                <?php foreach ($departments as $dept): ?>
                    <option value="<?= (int) $dept['id'] ?>"
                        <?= $deptId === (int) $dept['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dept['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Step Type</label>
            <label>
                <input type="checkbox" name="is_mandatory" value="1"
                    <?= (int) ($s['is_mandatory'] ?? 1) === 1 ? 'checked' : '' ?>>
                Mandatory (fails if no approver; optional steps auto-skip)
            </label>
        </div>
    </div>
<?php
}
?>

<div class="card">

    <div class="card-header">
        <div class="header-left">
            <h5>
                <i class="fa-solid fa-list-ol"></i>
                Configure Steps – <?= htmlspecialchars($workflow['name']) ?>
            </h5>
        </div>

        <div class="header-actions">
            <a href="/settings/workflows" class="btn btn-secondary btn-sm">
                Back
            </a>
        </div>
    </div>

    <div class="card-body">

        <!-- Add Step Form -->
        <form method="POST" action="/settings/workflows/<?= (int) $workflow['id'] ?>/steps">
            <input type="hidden" name="_token" value="<?= $token ?>">

            <?php workflow_step_fields(
                ['is_mandatory' => 1, 'approval_mode' => 'any', 'department_source' => 'none'],
                $roles,
                $departments,
                'add'
            ); ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Step
                </button>
            </div>
        </form>

        <div class="section-divider"></div>

        <!-- Existing Steps -->
        <div class="section-header">
            <h6>Existing Steps</h6>
        </div>

        <div class="table-card">

            <?php if (empty($steps)): ?>
                <div class="alert alert-info">
                    No steps configured yet.
                </div>
            <?php else: ?>

                <table class="table">
                    <thead>
                        <tr>
                            <th width="70">Order</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Approvals</th>
                            <th>Scope</th>
                            <th>Type</th>
                            <th class="text-end" width="90">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($steps as $step): $sid = (int) $step['id']; ?>
                            <tr>
                                <td><?= (int) $step['step_order'] ?></td>
                                <td><?= htmlspecialchars($step['name']) ?></td>
                                <td>
                                    <?= htmlspecialchars($step['role_name']) ?>
                                    <?php if (!empty($step['fallback_role_name'])): ?>
                                        <br><small>fallback: <?= htmlspecialchars($step['fallback_role_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $step['approval_mode'] === 'all' ? 'All (unanimous)' : 'Any (first)' ?></td>
                                <td>
                                    <?= htmlspecialchars(match ($step['department_source']) {
                                        'step'     => 'Fixed: ' . ($step['department_name'] ?? '—'),
                                        'gatepass' => "Requester's dept",
                                        default    => 'Whole tenant',
                                    }) ?>
                                </td>
                                <td>
                                    <?= (int) $step['is_mandatory'] === 1
                                        ? '<span class="badge badge-danger">Mandatory</span>'
                                        : '<span class="badge badge-secondary">Optional</span>' ?>
                                </td>
                                <td class="table-actions justify-content-end">
                                    <button type="button" class="btn btn-sm btn-secondary"
                                            data-toggle-step-edit="<?= $sid ?>">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                            <tr data-step-edit-row="<?= $sid ?>" hidden>
                                <td colspan="7">
                                    <form method="POST"
                                          action="/settings/workflows/<?= (int) $workflow['id'] ?>/steps/<?= $sid ?>">
                                        <input type="hidden" name="_token" value="<?= $token ?>">

                                        <?php workflow_step_fields($step, $roles, $departments, 'step' . $sid); ?>

                                        <div class="form-actions">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                Save Step
                                            </button>

                                            <button type="submit" class="btn btn-danger btn-sm"
                                                    formaction="/settings/workflows/<?= (int) $workflow['id'] ?>/steps/<?= $sid ?>/delete"
                                                    onclick="return confirm('Delete this step? It must have no approvals on it.');">
                                                Delete Step
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-toggle-step-edit]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var row = document.querySelector('[data-step-edit-row="' + btn.dataset.toggleStepEdit + '"]');
        if (row) row.hidden = !row.hidden;
    });
});

function syncDeptSelect(select) {
    var target = document.getElementById(select.dataset.target);
    if (!target) return;
    target.disabled = select.value !== 'step';
}

document.querySelectorAll('.js-dept-source').forEach(function (select) {
    select.addEventListener('change', function () { syncDeptSelect(select); });
    syncDeptSelect(select);
});
</script>
