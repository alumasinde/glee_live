<?php

use App\Core\View;

?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-user-check"></i> Visitors
    </h1>

    <div class="page-actions">
        <?php if (can('visitors.create')): ?>
            <a href="/visitors/create" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> New Visitor
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="list-toolbar">
    <?php View::render('components/global-search', [
        'action'      => '/visitors',
        'placeholder' => 'Search by name, ID number or company…',
    ]); ?>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($visitors),
        'noun'  => 'visitor',
    ]); ?>

    <?php if (empty($visitors)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-user-check"></i>
            <div class="empty-state-title">
                <?= trim((string) ($_GET['q'] ?? '')) !== '' ? 'No matching visitors' : 'No visitors yet' ?>
            </div>
            <div class="empty-state-text">
                <?= trim((string) ($_GET['q'] ?? '')) !== ''
                    ? 'Try a different name, ID number or company.'
                    : 'Register a visitor to start tracking their visits.' ?>
            </div>
            <?php if (can('visitors.create')): ?>
                <a href="/visitors/create" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> New Visitor
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Visitor</th>
                        <th>ID</th>
                        <th>Company</th>
                        <th>Risk</th>
                        <th>Status</th>
                        <th>Visits</th>
                        <th class="text-end" width="200">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitors as $v): ?>
                        <?php
                            $id  = (int) $v['id'];
                            $risk = (int) ($v['risk_score'] ?? 0);
                            $riskClass = $risk >= 70 ? 'badge-danger' : ($risk >= 40 ? 'badge-warning' : 'badge-success');
                        ?>
                        <tr>
                            <td>
                                <a class="cell-main" href="/visitors/<?= $id ?>">
                                    <?= htmlspecialchars($v['first_name'] . ' ' . $v['last_name']) ?>
                                </a>
                            </td>

                            <td>
                                <span class="cell-sub u-mt-0">
                                    <?= htmlspecialchars($v['id_type_name'] ?? '—') ?>
                                </span>
                                <span class="cell-mono"><?= htmlspecialchars($v['id_number'] ?? '—') ?></span>
                            </td>

                            <td><?= htmlspecialchars($v['company_name'] ?? '—') ?></td>

                            <td>
                                <span class="badge <?= $riskClass ?>"><?= $risk ?></span>
                            </td>

                            <td>
                                <?php if ((int) $v['is_blacklisted'] === 1): ?>
                                    <span class="badge badge-danger">Blacklisted</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Clear</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge bg-primary"><?= (int) ($v['total_visits'] ?? 0) ?></span>
                            </td>

                            <td class="table-actions justify-content-end">
                                <a href="/visitors/<?= $id ?>" class="btn btn-sm btn-ghost btn-icon" title="View">
                                    <i class="fa-solid fa-eye"></i>
                                </a>

                                <?php if (can('visitors.update')): ?>
                                    <a href="/visitors/<?= $id ?>/edit" class="btn btn-sm btn-ghost btn-icon" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ((int) $v['is_blacklisted'] === 1): ?>
                                    <?php if (can('visitors.unblacklist')): ?>
                                        <form method="POST" action="/visitors/<?= $id ?>/unblacklist" class="table-action-button">
                                            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                            <button class="btn btn-sm btn-soft-success">Unblacklist</button>
                                        </form>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (can('visitors.blacklist')): ?>
                                        <form method="POST" action="/visitors/<?= $id ?>/blacklist" class="table-action-button"
                                              data-confirm="Blacklist this visitor?">
                                            <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                                            <button class="btn btn-sm btn-secondary">Blacklist</button>
                                        </form>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (can('visits.create')): ?>
                                    <a href="/visits/create?visitor_id=<?= $id ?>" class="btn btn-sm btn-primary">
                                        New Visit
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
