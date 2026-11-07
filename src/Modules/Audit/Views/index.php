<?php
/**
 * Audit trail listing.
 *
 * @var list<array> $entries
 * @var array       $data  ['meta' => pagination meta]
 * @var array       $options  actions / entity_types / users
 * @var array       $filters
 */

use App\Core\View;

$sortDir  = strtolower(($sort_dir ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$sortBy   = (string) ($sort_by ?? 'created_at');

$sortLink = static function (string $column) use ($sortBy, $sortDir): string {
    $query = $_GET;
    $query['sort_by']  = $column;
    $query['sort_dir'] = ($sortBy === $column && $sortDir === 'asc') ? 'desc' : 'asc';
    unset($query['page']);

    return '?' . htmlspecialchars(http_build_query($query), ENT_QUOTES);
};

$arrow = static function (string $column) use ($sortBy, $sortDir): string {
    if ($sortBy !== $column) {
        return '';
    }

    return $sortDir === 'asc'
        ? ' <i class="fa-solid fa-arrow-up-short-wide u-fs-070"></i>'
        : ' <i class="fa-solid fa-arrow-down-short-wide u-fs-070"></i>';
};
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-clock-rotate-left"></i> <?= htmlspecialchars($title ?? 'Audit Trail') ?>
    </h1>
    <div class="page-actions">
        <a class="btn btn-sm btn-outline" href="/reports/audit-logs">
            <i class="fa-solid fa-file-export"></i> Export report
        </a>
    </div>
</div>

<form method="GET" action="/audit" class="filter-bar">
    <div class="filter-grid">
        <div class="form-group">
            <label for="search">Search</label>
            <input class="form-control" type="text" id="search" name="search"
                   value="<?= htmlspecialchars($search ?? '', ENT_QUOTES) ?>"
                   placeholder="Action, message or actor">
        </div>

        <div class="form-group">
            <label for="action">Action</label>
            <select class="form-select" id="action" name="action">
                <option value="">All actions</option>
                <?php foreach ($options['actions'] as $value => $label): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>"
                        <?= ($filters['action'] ?? '') === (string) $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="entity_type">Entity</label>
            <select class="form-select" id="entity_type" name="entity_type">
                <option value="">All entities</option>
                <?php foreach ($options['entity_types'] as $value): ?>
                    <option value="<?= htmlspecialchars((string) $value, ENT_QUOTES) ?>"
                        <?= ($filters['entity_type'] ?? '') === (string) $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars(ucfirst((string) $value), ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="user_id">Actor</label>
            <select class="form-select" id="user_id" name="user_id">
                <option value="0">Anyone</option>
                <?php foreach ($options['users'] as $id => $label): ?>
                    <option value="<?= (int) $id ?>"
                        <?= (int) ($filters['user_id'] ?? 0) === (int) $id ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) $label, ENT_QUOTES) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="date_from">From</label>
            <input class="form-control" type="date" id="date_from" name="date_from"
                   value="<?= htmlspecialchars($filters['date_from'] ?? '', ENT_QUOTES) ?>">
        </div>

        <div class="form-group">
            <label for="date_to">To</label>
            <input class="form-control" type="date" id="date_to" name="date_to"
                   value="<?= htmlspecialchars($filters['date_to'] ?? '', ENT_QUOTES) ?>">
        </div>
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        <a class="btn btn-sm btn-outline" href="/audit">Reset</a>
    </div>
</form>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => (int) ($data['meta']['total'] ?? count($entries)),
        'noun'  => 'audit entry',
    ]); ?>

    <?php if (empty($entries)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <div class="empty-state-title">Nothing recorded</div>
            <div class="empty-state-text">No audit entries match these filters.</div>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th width="150"><a class="col-sort" href="<?= $sortLink('created_at') ?>">When<?= $arrow('created_at') ?></a></th>
                        <th><a class="col-sort" href="<?= $sortLink('action') ?>">Action<?= $arrow('action') ?></a></th>
                        <th><a class="col-sort" href="<?= $sortLink('user') ?>">Actor<?= $arrow('user') ?></a></th>
                        <th><a class="col-sort" href="<?= $sortLink('entity') ?>">Entity<?= $arrow('entity') ?></a></th>
                        <th>Summary</th>
                        <th width="70"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td class="cell-date"><?= format_date($entry['created_at'] ?? null) ?></td>
                            <td><span class="chip"><?= htmlspecialchars($entry['action'] ?? '') ?></span></td>
                            <td class="cell-main">
                                <?= htmlspecialchars(trim((string) ($entry['user_name'] ?? '')) ?: 'System') ?>
                            </td>
                            <td>
                                <?php if (!empty($entry['entity_type'])): ?>
                                    <?= htmlspecialchars(ucfirst((string) $entry['entity_type'])) ?>
                                    <?php if (!empty($entry['entity_id'])): ?>
                                        <span class="cell-date">#<?= (int) $entry['entity_id'] ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="cell-date">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($entry['message'] ?? '') ?: '—' ?></td>
                            <td class="table-actions justify-content-end">
                                <a class="btn btn-sm btn-outline" href="/audit/<?= (int) $entry['id'] ?>">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php include base_path('resources/views/partials/pagination.php'); ?>
    <?php endif; ?>
</div>
