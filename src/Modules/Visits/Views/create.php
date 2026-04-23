<?php
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

$visitor = $visitor ?? null;
?>

<div class="form-group">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Create New Visit</h4>
        <a href="/visits" class="btn btn-secondary btn-sm">Back</a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">

            <form method="POST" action="/visits">

                <!-- VISITOR NAME -->
                <div class="mb-3">
                    <label class="form-label">Visitor</label>

                    <?php if ($visitor): ?>
                        <input type="text"
                               class="form-control"
                               value="<?= htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']) ?>"
                               readonly>

                        <input type="hidden"
                               name="visitor_id"
                               value="<?= (int) $visitor['id'] ?>">
                    <?php else: ?>
                        <div class="alert alert-warning">
                            No visitor selected.
                        </div>
                    <?php endif; ?>
                </div>

        <div class="mb-3">
    <label class="form-label">Host</label>
    <select name="host_user_id"
            id="hostSelect"
            class="form-select"
            required>

        <option value="">Select host</option>

        <?php foreach ($hosts ?? [] as $host): ?>
            <option value="<?= (int) $host['id'] ?>"
                    data-department="<?= (int) ($host['department_id'] ?? 0) ?>"
                <?= (($old['host_user_id'] ?? '') == $host['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars(
                    trim(($host['first_name'] ?? '') . ' ' . ($host['last_name'] ?? ''))
                ) ?>
            </option>
        <?php endforeach; ?>

    </select>
</div>

                <!-- DEPARTMENT (AUTO SELECTED) -->
                <div class="mb-3">
                    <label class="form-label">Department</label>
                    <select name="department_id"
                            id="departmentSelect"
                            class="form-select"
                            required>
                        <option value="">Select department</option>
                        <?php foreach ($departments ?? [] as $dept): ?>
                            <option value="<?= $dept['id'] ?>">
                                <?= htmlspecialchars($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- VISIT TYPE -->
                <div class="mb-3">
                    <label class="form-label">Visit Type</label>
                    <select name="visit_type_id" class="form-select">
                        <option value="">Select type</option>
                        <?php foreach ($visitTypes ?? [] as $type): ?>
                            <option value="<?= $type['id'] ?>">
                                <?= htmlspecialchars($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- PURPOSE -->
                <div class="mb-3">
                    <label class="form-label">Purpose</label>
                    <textarea name="purpose"
                              class="form-control"
                              rows="3"
                              required><?= htmlspecialchars($old['purpose'] ?? '') ?></textarea>
                </div>

                <!-- EXPECTED IN -->
                <div class="mb-3">
                    <label class="form-label">Expected Check-In</label>
                    <input type="datetime-local"
                           name="expected_in"
                           class="form-control"
                           value="<?= htmlspecialchars($old['expected_in'] ?? '') ?>">
                </div>

                <!-- EXPECTED OUT -->
                <div class="mb-3">
                    <label class="form-label">Expected Check-Out</label>
                    <input type="datetime-local"
                           name="expected_out"
                           class="form-control"
                           value="<?= htmlspecialchars($old['expected_out'] ?? '') ?>">
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">
                        Create Visit
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<!-- AUTO SELECT DEPARTMENT SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const hostSelect = document.getElementById('hostSelect');
    const deptSelect = document.getElementById('departmentSelect');

    function syncDepartment() {
        const selected = hostSelect.options[hostSelect.selectedIndex];
        if (!selected) return;

        const departmentId = selected.getAttribute('data-department');

        if (departmentId && deptSelect) {
            deptSelect.value = departmentId;
        }
    }

    // On host change
    hostSelect.addEventListener('change', syncDepartment);

    // On page load (important for validation reload)
    if (hostSelect.value) {
        syncDepartment();
    }

});
</script>