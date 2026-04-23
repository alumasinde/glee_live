<?php /** @var array $workflow */ ?>
<?php /** @var array $steps */ ?>
<?php /** @var array $roles */ ?>

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
        <form method="POST" action="/settings/workflows/<?= (int)$workflow['id'] ?>/steps">

            <div class="form-grid">

                <div class="form-group">
                    <label for="step_order">Step Order <span class="required">*</span></label>
                    <input
                        type="number"
                        id="step_order"
                        name="step_order"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['step_order'] ?? '') ?>"
                        placeholder="1, 2, 3..."
                        min="1"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="step_name">Step Name <span class="required">*</span></label>
                    <input
                        type="text"
                        id="step_name"
                        name="step_name"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['step_name'] ?? '') ?>"
                        placeholder="e.g. HOD Approval"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="role_id">Assign Role <span class="required">*</span></label>
                    <select id="role_id" name="role_id" class="form-control" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                            <option
                                value="<?= (int)$role['id'] ?>"
                                <?= ((int)($_POST['role_id'] ?? 0) === (int)$role['id']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($role['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

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
                            <th width="80">Order</th>
                            <th>Name</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($steps as $step): ?>
                            <tr>
                                <td><?= (int)$step['step_order'] ?></td>
                                <td><?= htmlspecialchars($step['name']) ?></td>
                                <td><?= htmlspecialchars($step['role_name']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>

        </div>

    </div>
</div>