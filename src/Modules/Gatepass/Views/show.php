<h1 class="page-heading">
    Gatepass #<?= htmlspecialchars($gatepass['gatepass_number']) ?>
</h1>

<div class="form-card">

    <!-- STATUS -->
    <div class="info-row">
        <strong>Status:</strong>
        <span class="badge badge-status">
            <?= htmlspecialchars($gatepass['status_name'] ?? 'Unknown') ?>
        </span>
    </div>

    <!-- TYPE -->
    <div class="info-row">
        <strong>Gatepass Type:</strong>
        <?= htmlspecialchars($gatepass['gatepass_type_name'] ?? 'N/A') ?>
    </div>

    <!-- PURPOSE -->
    <div class="info-row">
        <strong>Purpose:</strong>
        <p><?= nl2br(htmlspecialchars($gatepass['purpose'] ?? '')) ?></p>
    </div>

    <!-- CREATED BY -->
    <div class="info-row">
        <strong>Created By:</strong>
        <?= htmlspecialchars(
            trim(($gatepass['first_name'] ?? '') . ' ' . ($gatepass['last_name'] ?? ''))
        ) ?: 'N/A' ?>
    </div>

    <!-- CREATED AT -->
    <div class="info-row">
        <strong>Created At:</strong>
        <?= !empty($gatepass['created_at'])
            ? htmlspecialchars(date('Y-m-d H:i', strtotime($gatepass['created_at'])))
            : 'N/A' ?>
    </div>

    <!-- EXPECTED RETURN DATE -->
    <div class="info-row">
        <strong>Expected Return Date:</strong>
        <?= !empty($gatepass['expected_return_date'])
            ? htmlspecialchars(date('Y-m-d', strtotime($gatepass['expected_return_date'])))
            : 'Not specified' ?>
    </div>

    <!-- RETURNABLE -->
    <div class="info-row">
        <strong>Returnable:</strong>
        <?= !empty($gatepass['is_returnable']) ? 'Yes' : 'No' ?>
    </div>

    <!-- NEEDS APPROVAL -->
    <div class="info-row">
        <strong>Needs Approval:</strong>
        <?= !empty($gatepass['needs_approval']) ? 'Yes' : 'No' ?>
    </div>

    <hr>

    <!-- ITEMS -->
    <h3>Items</h3>

    <?php if (!empty($items)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Description</th>
                    <th>Qty</th>
                    <th>Serial</th>
                    <th>Returnable</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['item_name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['description'] ?? '') ?></td>
                        <td><?= (int)($item['quantity'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($item['serial_number'] ?? '') ?></td>
                        <td><?= !empty($item['is_returnable']) ? 'Yes' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No items attached to this gatepass.</p>
    <?php endif; ?>

    <div class="form-actions">

        <a href="/gatepasses/<?= (int)$gatepass['id'] ?>/edit"
           class="btn btn-primary">
            Edit
        </a>

        <form method="POST"
              action="/gatepasses/<?= (int)$gatepass['id'] ?>/delete"
              style="display:inline;"
              onsubmit="return confirm('Are you sure you want to delete this gatepass?');">

            <!-- CSRF (recommended) -->
            <?php if (!empty($_SESSION['csrf'])): ?>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
            <?php endif; ?>

            <button type="submit" class="btn btn-danger">
                Delete
            </button>
        </form>

        <a href="/gatepasses" class="btn btn-secondary">
            Back
        </a>

    </div>

</div>