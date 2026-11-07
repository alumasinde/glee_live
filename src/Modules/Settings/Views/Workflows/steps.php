<?php
/** @var array $workflow */
/** @var array $steps */
/** @var array $roles */
/** @var array $departments */
/** @var array $people */
/** @var array $holders_by_role */

$token = \App\Middleware\CSRFMiddleware::token();

/**
 * Who the step would actually staff, using the same rules the engine applies.
 * Role steps count their holders (per department when the scope narrows them);
 * roster steps name the ticked people.
 */
function workflow_step_resolves(array $step, array $holdersByRole): string
{
    if (($step['approver_kind'] ?? 'role') === 'users') {
        $roster = $step['approvers'] ?? [];

        if (!$roster) {
            return 'No one selected yet';
        }

        $names = array_map(
            static fn(array $p): string => $p['name'] . ((int) $p['is_active'] === 1 ? '' : ' (inactive)'),
            $roster
        );

        return count($names) <= 4
            ? implode(', ', $names)
            : implode(', ', array_slice($names, 0, 3)) . ' +' . (count($names) - 3) . ' more';
    }

    $holders = $holdersByRole[(int) ($step['role_id'] ?? 0)] ?? [];

    if ($step['department_source'] !== 'none') {
        $wanted = $step['department_source'] === 'step' ? (int) ($step['department_id'] ?? 0) : null;

        $byDept = [];
        foreach ($holders as $holder) {
            if ($wanted !== null && (int) $holder['department_id'] !== $wanted) {
                continue;
            }

            $dept = $holder['department_name'] ?? 'No department';
            $byDept[$dept][] = $holder['name'];
        }

        if (!$byDept) {
            return $wanted !== null
                ? 'No holder of this role in that department'
                : 'No holder of this role in any department';
        }

        if ($wanted === null) {
            $parts = array_map(
                static fn(string $dept, array $list): string => $dept . ': ' . implode(', ', $list),
                array_keys($byDept),
                array_values($byDept)
            );

            return 'Matched by the requester\'s department — ' . implode(' · ', $parts);
        }

        return implode(', ', reset($byDept));
    }

    $names = array_column($holders, 'name');

    if (!$names) {
        return 'Nobody holds this role';
    }

    return count($names) <= 4
        ? implode(', ', $names)
        : count($names) . ' holders — ' . implode(', ', array_slice($names, 0, 3)) . '…';
}

/**
 * One fieldset shared by the add form and every inline edit form.
 * approval_mode is a single checkbox: absent means 'any'.
 */
function workflow_step_fields(
    array $step,
    array $roles,
    array $departments,
    array $people,
    array $holdersByRole,
    string $prefix
): void {
    $kind      = $step['approver_kind'] ?? 'role';
    $isUsers   = $kind === 'users';
    $sel       = fn(string $key, string $option, $default = '') => (string) ($step[$key] ?? $default) === $option ? 'selected' : '';
    $pickedIds = array_map('intval', array_column($step['approvers'] ?? [], 'user_id'));
    $roleHolders = (int) count($holdersByRole[(int) ($step['role_id'] ?? 0)] ?? []);
    $value     = htmlspecialchars((string) ($step['name'] ?? ''), ENT_QUOTES);
    $order     = (int) ($step['step_order'] ?? 0);
?>
    <div class="ws-form" data-ws-form>
        <div class="ws-field ws-span-1">
            <label class="ws-label" for="<?= $prefix ?>-order">Order</label>
            <input type="number" id="<?= $prefix ?>-order" name="step_order" class="form-control"
                   min="1" required value="<?= $order > 0 ? $order : '' ?>" placeholder="1">
        </div>

        <div class="ws-field ws-span-4">
            <label class="ws-label" for="<?= $prefix ?>-name">Step name</label>
            <input type="text" id="<?= $prefix ?>-name" name="step_name" class="form-control"
                   placeholder="e.g. HOD approval" required maxlength="150" value="<?= $value ?>">
        </div>

        <div class="ws-field ws-span-3">
            <label class="ws-label" for="<?= $prefix ?>-kind">Approve by</label>
            <select id="<?= $prefix ?>-kind" name="approver_kind" class="form-control js-ws-kind">
                <option value="role"  <?= $isUsers ? '' : 'selected' ?>>Role</option>
                <option value="users" <?= $isUsers ? 'selected' : '' ?>>Named people</option>
            </select>
        </div>

        <div class="ws-checks ws-span-4">
            <label class="gp-checkbox">
                <input type="checkbox" name="is_mandatory" value="1"
                    <?= (int) ($step['is_mandatory'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span class="gp-checkbox__box"></span>
                <span class="gp-checkbox__label">Mandatory</span>
            </label>

            <label class="gp-checkbox">
                <input type="checkbox" name="approval_mode" value="all"
                    <?= ($step['approval_mode'] ?? 'any') === 'all' ? 'checked' : '' ?>>
                <span class="gp-checkbox__box"></span>
                <span class="gp-checkbox__label">Unanimous (every approver must sign)</span>
            </label>
        </div>

        <div class="ws-contents" data-ws-role>
            <div class="ws-field ws-span-3">
                <label class="ws-label" for="<?= $prefix ?>-role">Role</label>
                <select id="<?= $prefix ?>-role" name="role_id" class="form-control js-ws-role">
                    <option value="">Select role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>"
                            <?= (int) ($step['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ws-field ws-span-4">
                <label class="ws-label" for="<?= $prefix ?>-scope">Which holders</label>
                <select id="<?= $prefix ?>-scope" name="department_source" class="form-control js-ws-scope"
                        data-target="<?= $prefix ?>-dept">
                    <option value="none"     <?= $sel('department_source', 'none') ?>>Anyone in the tenant with the role</option>
                    <option value="gatepass" <?= $sel('department_source', 'gatepass') ?>>Same department as the requester (HOD rule)</option>
                    <option value="step"     <?= $sel('department_source', 'step') ?>>Only one fixed department</option>
                </select>
            </div>

            <div class="ws-field ws-span-3">
                <label class="ws-label" for="<?= $prefix ?>-dept">Department</label>
                <select id="<?= $prefix ?>-dept" name="department_id" class="form-control">
                    <option value="">—</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int) $dept['id'] ?>"
                            <?= (int) ($step['department_id'] ?? 0) === (int) $dept['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="ws-field ws-span-5">
            <label class="ws-label" for="<?= $prefix ?>-fallback">Fallback role</label>
            <select id="<?= $prefix ?>-fallback" name="fallback_role_id" class="form-control">
                <option value="">— None —</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['id'] ?>"
                        <?= (int) ($step['fallback_role_id'] ?? 0) === (int) $role['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="ws-hint">Used only when the step resolves to nobody.</span>
        </div>

        <div class="ws-field ws-span-12" data-ws-people<?= $isUsers ? '' : ' hidden' ?>>
            <div class="ws-people-head">
                <span class="ws-label">Approvers</span>
                <input type="search" class="form-control ws-people-search" placeholder="Filter by name or department"
                       data-ws-filter>
                <button type="button" class="btn btn-sm btn-secondary" data-ws-check-all>Check visible</button>
                <button type="button" class="btn btn-sm btn-secondary" data-ws-clear>Clear</button>
                <span class="ws-count" data-ws-count></span>
            </div>

            <div class="ws-people">
                <?php foreach ($people as $person): ?>
                    <label class="ws-person" data-ws-search="<?= htmlspecialchars(
                        strtolower($person['name'] . ' ' . ($person['department_name'] ?? '')),
                        ENT_QUOTES
                    ) ?>">
                        <input type="checkbox" name="user_ids[]" value="<?= (int) $person['id'] ?>"
                            <?= in_array((int) $person['id'], $pickedIds, true) ? 'checked' : '' ?>>
                        <span class="ws-person__name"><?= htmlspecialchars($person['name']) ?></span>
                        <span class="ws-person__meta"><?= htmlspecialchars($person['department_name'] ?? 'No department') ?>
                            <?= $person['role_names'] !== null ? ' · ' . htmlspecialchars($person['role_names']) : '' ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ws-resolve ws-span-12">
            <span class="ws-label">Resolves to</span>
            <span data-ws-resolve><?= htmlspecialchars(workflow_step_resolves(
                $step + ['approvers' => $step['approvers'] ?? []],
                $holdersByRole
            )) ?></span>
            <span class="ws-hint">Re-counts when you save; the engine always reads live members.</span>
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
            <a href="/settings/workflows" class="btn btn-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="card-body">

        <form method="POST" action="/settings/workflows/<?= (int) $workflow['id'] ?>/steps">
            <input type="hidden" name="_token" value="<?= $token ?>">

            <?php workflow_step_fields(
                ['is_mandatory' => 1, 'approval_mode' => 'any', 'department_source' => 'none', 'approvers' => []],
                $roles,
                $departments,
                $people,
                $holders_by_role,
                'add'
            ); ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Add Step
                </button>
            </div>
        </form>

        <div class="section-divider"></div>

        <div class="section-header">
            <h6>Existing Steps</h6>
        </div>

        <div class="table-card">

            <?php if (empty($steps)): ?>
                <div class="alert alert-info">No steps configured yet.</div>
            <?php else: ?>

                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th width="50">#</th>
                            <th>Step</th>
                            <th>Approves by</th>
                            <th>Resolves to</th>
                            <th width="80">Signing</th>
                            <th width="110">Required</th>
                            <th class="text-end" width="70">Edit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($steps as $step): $sid = (int) $step['id']; ?>
                            <tr>
                                <td><?= (int) $step['step_order'] ?></td>
                                <td><?= htmlspecialchars($step['name']) ?></td>
                                <td>
                                    <?php if (($step['approver_kind'] ?? 'role') === 'users'): ?>
                                        <span class="badge badge-info">Named people</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($step['role_name'] ?? '—') ?>
                                        <span class="ws-hint">
                                            (<?= htmlspecialchars(match ($step['department_source']) {
                                                'step'     => 'fixed department',
                                                'gatepass' => "requester's department",
                                                default    => 'whole tenant',
                                            }) ?>)
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($step['fallback_role_name'])): ?>
                                        <br><small class="ws-hint">fallback: <?= htmlspecialchars($step['fallback_role_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="ws-resolve"><?= htmlspecialchars(workflow_step_resolves($step, $holders_by_role)) ?></td>
                                <td><?= $step['approval_mode'] === 'all' ? 'All' : 'Any' ?></td>
                                <td>
                                    <?= (int) $step['is_mandatory'] === 1
                                        ? '<span class="badge badge-danger">Mandatory</span>'
                                        : '<span class="badge badge-secondary">Optional</span>' ?>
                                </td>
                                <td class="table-actions justify-content-end">
                                    <button type="button" class="btn btn-sm btn-secondary"
                                            data-toggle-target='[data-step-edit-row="<?= $sid ?>"]'>Edit</button>
                                </td>
                            </tr>
                            <tr data-step-edit-row="<?= $sid ?>" hidden>
                                <td colspan="7" class="ws-edit-cell">
                                    <form method="POST"
                                          action="/settings/workflows/<?= (int) $workflow['id'] ?>/steps/<?= $sid ?>">
                                        <input type="hidden" name="_token" value="<?= $token ?>">

                                        <?php workflow_step_fields(
                                            $step,
                                            $roles,
                                            $departments,
                                            $people,
                                            $holders_by_role,
                                            'step' . $sid
                                        ); ?>

                                        <div class="form-actions">
                                            <button type="submit" class="btn btn-primary btn-sm">Save Step</button>
                                            <button type="submit" class="btn btn-danger btn-sm"
                                                    formaction="/settings/workflows/<?= (int) $workflow['id'] ?>/steps/<?= $sid ?>/delete"
                                                    data-confirm="Delete this step? It must have no approvals on it.">
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

<script src="/assets/js/pages/steps.js" defer></script>
