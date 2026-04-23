<h2><?= $title ?></h2>

<?php include __DIR__ . '/partials/filters.php'; ?>

<?php if (!empty($data['data'])): ?>

<table>
    <thead>
        <tr>
            <?php include __DIR__ . '/partials/table-header.php'; ?>
            <?php sortLink('created_at', 'Date'); ?>
            <th>Action</th>
            <th>Entity</th>
            <th>User</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($data['data'] as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><?= htmlspecialchars($row['action']) ?></td>
                <td><?= htmlspecialchars($row['entity_type']) ?></td>
                <td><?= htmlspecialchars($row['user_id']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php include __DIR__ . '/partials/pagination.php'; ?>

<?php else: ?>
    <?php include __DIR__ . '/partials/empty.php'; ?>
<?php endif; ?>