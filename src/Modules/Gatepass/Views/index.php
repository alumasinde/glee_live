<?php

use App\Core\View;

?>
<div class="page-header">
<h1 class="page-heading">
    <i class="fa-solid fa-id-card"></i> Gatepasses</h1>

<div class="page-actions">
    <?php if (can('gatepass.scan')): ?>
        <a href="/gatepasses/scan" class="btn btn-secondary">
            <i class="fa-solid fa-qrcode"></i> Scan
        </a>
    <?php endif; ?>

    <?php if (can('gatepass.create')): ?>
        <a href="/gatepasses/create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Create Gatepass
        </a>
    <?php endif; ?>
</div>
</div>

<?php View::render('components/global-search', ['action' => '/gatepasses']); ?>

<div class="table-card">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Number</th>
                <th>Status</th>
                <th>Gatepass Type</th>
                <th>Returnable</th>
                <th>Needs Approval</th>
                <th>Created</th>
                <th width="180">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($gatepasses)): ?>
            <tr>
                <td colspan="8" class="text-center">No gatepasses found.</td>
            </tr>
        <?php endif; ?>

        <?php foreach ($gatepasses as $g): ?>
            <tr>
                <td><?= $g['id'] ?></td>
                <td><?= htmlspecialchars($g['gatepass_number']) ?></td>
                <td>
                    <span class="badge badge-<?= strtolower($g['status_name']) ?>">
                        <?= htmlspecialchars($g['status_name']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($g['gatepass_type_name']) ?></td>
                <td><?= $g['is_returnable'] ? 'Yes' : 'No' ?></td>
                <td><?= $g['needs_approval'] ? 'Yes' : 'No' ?></td>
                <td><?= $g['created_at'] ?></td>
                
                <td class="table-actions">
    <a href="/gatepasses/<?= (int)$g['id'] ?>" class="btn view-btn">
        View
    </a>

    <?php if (can('gatepass.update')): ?>
        <a href="/gatepasses/<?= (int)$g['id'] ?>/edit"
           class="btn edit-btn ">
            Edit
        </a>
    <?php endif; ?>

<?php $id = (int)$g['id'];  ?>

<?php if (!empty($g['can_checkin']) && can('gatepass.checkin')): ?>
    <form method="POST" action="/gatepasses/<?= $id ?>/checkin" class="table-action-button">
        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
        <button type="submit" class="btn checkin-btn">
            Check In
        </button>
    </form>
<?php endif; ?>

<?php if (!empty($g['can_checkout']) && can('gatepass.checkout')): ?>
    <form method="POST" action="/gatepasses/<?= $id ?>/checkout" class="table-action-button">
        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
        <button type="submit" class="btn checkout-btn">
            Check Out
        </button>
    </form>
<?php endif; ?>
 

</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>