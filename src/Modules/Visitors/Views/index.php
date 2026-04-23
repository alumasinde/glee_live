<?php

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">Visitors</h1>

    <div class="page-actions">
    <a href="/visitors/create" class="btn btn-primary">
         <i class="fa-solid fa-plus"></i> New Visitor
    </a>
</div>
</div>

<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>">
        <?= htmlspecialchars($_SESSION['flash']['message']) ?>
    </div>
    <?php unset($_SESSION['flash']); ?>
<?php endif; ?>

<?= View::render('components/global-search', ['action' => '/visitors']) ?>


<div class="table-card">
<table class="table">
 <thead>
        <tr>
            <th>Name</th>
            <th>ID Type</th>
            <th>ID Number</th>
            <th>Company</th>
            <th>Risk</th>
            <th>Status</th>
            <th>Visits</th>
            <th width="220">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php if (!empty($visitors)): ?>
         <?php else: ?>
        <tr>
            <td colspan="8" class="text-center">
                No visitors found.
            </td>
        </tr>
    <?php endif; ?>

        <?php foreach ($visitors as $v): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($v['first_name'] . ' ' . $v['last_name']) ?>
                </td>

                <td><?= htmlspecialchars($v['id_type_name'] ?? '-') ?></td>
                <td><?= htmlspecialchars($v['id_number'] ?? '-') ?></td>
                <td><?= htmlspecialchars($v['company_name'] ?? '-') ?></td>

                <td>
                    <?php
                        $risk = (int) ($v['risk_score'] ?? 0);
                        $riskClass = $risk >= 70
                            ? 'badge bg-danger'
                            : ($risk >= 40 ? 'badge bg-warning text-dark' : 'badge bg-success');
                    ?>
                    <span class="<?= $riskClass ?>">
                        <?= $risk ?>
                    </span>
                </td>

                <td>
                    <?php if ((int)$v['is_blacklisted'] === 1): ?>
                        <span class="badge bg-danger">Blacklisted</span>
                    <?php else: ?>
                        <span class="badge bg-success">Clear</span>
                    <?php endif; ?>
                </td>
                <td>
    <span class="badge bg-primary">
        <?= (int) ($v['total_visits'] ?? 0) ?>
    </span>
</td>

                <td class="table-actions">

                        <?php if ((int)$v['is_blacklisted'] === 1): ?>
                            <form method="POST" action="/visitors/<?= $v['id'] ?>/unblacklist">
                                <button class="btn btn-sm btn-success">
                                    Unblacklist
                                </button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="/visitors/<?= $v['id'] ?>/blacklist">
                                <button class="btn btn-sm btn-warning">
                                    Blacklist
                                </button>
                            </form>
                        <?php endif; ?>
<a href="/visits/create?visitor_id=<?= $v['id'] ?>"class="btn btn-sm btn-primary">New Visit</a>

<a href="/visitors/<?= (int)$v['id'] ?>/edit" class="btn btn-sm btn-secondary">
   Edit
</a>
<a href="/visitors/<?= $v['id'] ?>"class="btn btn-sm btn-info text-white">View</a>

                </td>
            </tr>
        <?php endforeach; ?>
   
    </tbody>
</table>
</div>
