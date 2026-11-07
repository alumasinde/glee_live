<?php

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-building"></i> Departments
    </h1>
    <div class="page-actions">
        <a href="/settings" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Settings
        </a>
    </div>
</div>

<!-- ===================== CREATE FORM ===================== -->
<div class="table-card mb-4">
    <div class="table-card-head">
        <span class="table-card-title">
            <i class="fa-solid fa-plus"></i> Add Department
        </span>
    </div>

    <div class="card-body u-maxw-560">
        <form method="POST" action="/settings/departments/create">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

            <div class="form-group">
                <label class="form-label" for="dept-name">Department Name</label>
                <input type="text"
                       id="dept-name"
                       name="name"
                       class="form-control"
                       placeholder="e.g. Human Resources"
                       required>
            </div>

            <div class="form-group">
                <label class="form-label" for="dept-code">Department Code</label>
                <input type="text"
                       id="dept-code"
                       name="code"
                       class="form-control"
                       placeholder="e.g. HR"
                       required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Department
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== LIST ===================== -->
<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($departments),
        'noun'  => 'department',
    ]); ?>

    <?php if (empty($departments)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-building"></i>
            <div class="empty-state-title">No departments yet</div>
            <div class="empty-state-text">
                Add your first department using the form above.
            </div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th width="90">Active</th>
                        <th>Created</th>
                        <th class="text-end" width="110">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td><span class="cell-main"><?= htmlspecialchars($dept->name) ?></span></td>
                            <td><span class="cell-mono"><?= htmlspecialchars($dept->code) ?></span></td>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           <?= $dept->isActive ? 'checked' : '' ?>
                                           data-department-toggle data-id="<?= (int) $dept->id ?>">
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td class="cell-date"><?= format_date($dept->createdAt ?? null) ?></td>
                            <td class="table-actions justify-content-end">
                                <button
                                    class="btn btn-sm btn-ghost btn-icon"
                                    title="Edit"
                                    data-open-form="#editModal"
                                    data-field-id="<?= (int) $dept->id ?>"
                                    data-field-name="<?= htmlspecialchars($dept->name, ENT_QUOTES) ?>"
                                    data-field-code="<?= htmlspecialchars($dept->code, ENT_QUOTES) ?>"
                                >
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button
                                    class="btn btn-sm btn-ghost btn-icon"
                                    title="Delete"
                                    data-department-delete data-id="<?= (int) $dept->id ?>"
                                >
                                    <i class="fa-solid fa-trash text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ===================== EDIT MODAL (simple inline JS version) ===================== -->
<div id="editModal" class="table-card mt-4 u-d-none">
    <div class="table-card-head">
        <span class="table-card-title">
            <i class="fa-solid fa-pen"></i> Edit Department
        </span>
    </div>

    <div class="card-body u-maxw-560">
        <form method="POST" action="/settings/departments/update">
            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
            <input type="hidden" name="id" id="edit_id">

            <div class="form-group">
                <label class="form-label" for="edit_name">Department Name</label>
                <input type="text" id="edit_name" name="name" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="edit_code">Department Code</label>
                <input type="text" id="edit_code" name="code" class="form-control" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-secondary" data-close="#editModal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- ===================== SIMPLE JS ACTIONS ===================== -->
<script src="/assets/js/pages/departments.js" defer></script>
