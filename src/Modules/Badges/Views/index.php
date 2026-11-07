<?php
/**
 * Badge operations list.
 *
 * @var array $badges   Paginated badge rows (BadgeRepository::list)
 * @var array $meta     Paginator meta: total, per_page, current_page, last_page, from, to
 * @var array $filters  Echoed filter values: status, visit_id, gatepass_id, holder_id
 * @var string $search  Active search term
 * @var array $holders  holder_id => name options for the filter bar
 */

use App\Core\View;
use App\Middleware\CSRFMiddleware;

$badges  = $badges ?? [];
$meta    = $meta ?? ['total' => count($badges), 'current_page' => 1, 'last_page' => 1];
$filters = $filters ?? [];
$holders = $holders ?? [];
$search  = trim((string) ($search ?? ($_GET['q'] ?? '')));

/** Null-safe HTML escape */
$e = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$canIssue  = can('badges.issue');
$canReturn = can('badges.return');
$token    = $canReturn ? CSRFMiddleware::token() : '';

/** Pagination link that keeps every active filter. */
$pageUrl = static function (int $page): string {
    $query = $_GET;
    $query['page'] = $page;
    return '?' . htmlspecialchars(http_build_query($query), ENT_QUOTES, 'UTF-8');
};
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-id-badge"></i> Badge Operations
    </h1>

    <div class="page-actions">
        <?php if ($canIssue): ?>
            <a href="/badges/create" class="btn btn-primary">
                <i class="fa-solid fa-plus"></i> Issue Badge
            </a>
        <?php endif; ?>
    </div>
</div>

<form method="GET" action="/badges" class="report-filters">

    <div class="form-group mb-0">
        <label class="form-label u-fs-072">Search</label>
        <input
            type="text"
            name="q"
            value="<?= $e($search) ?>"
            placeholder="Badge code, holder, company…"
            class="u-minw-220"
        >
    </div>

    <div class="form-group mb-0">
        <label class="form-label u-fs-072">Status</label>
        <select name="status" class="u-minw-140">
            <option value="">All</option>
            <option value="issued"   <?= ($filters['status'] ?? '') === 'issued'   ? 'selected' : '' ?>>Issued</option>
            <option value="returned" <?= ($filters['status'] ?? '') === 'returned' ? 'selected' : '' ?>>Returned</option>
        </select>
    </div>

    <div class="form-group mb-0">
        <label class="form-label u-fs-072">Visit</label>
        <input
            type="number"
            name="visit_id"
            min="1"
            value="<?= (int) ($filters['visit_id'] ?? 0) ?: '' ?>"
            placeholder="Visit ID"
            class="u-minw-120"
        >
    </div>

    <div class="form-group mb-0">
        <label class="form-label u-fs-072">Gatepass</label>
        <input
            type="number"
            name="gatepass_id"
            min="1"
            value="<?= (int) ($filters['gatepass_id'] ?? 0) ?: '' ?>"
            placeholder="Gatepass ID"
            class="u-minw-130"
        >
    </div>

    <div class="form-group mb-0">
        <label class="form-label u-fs-072">Holder</label>
        <select name="holder_id" class="u-minw-170">
            <option value="">All holders</option>
            <?php foreach ($holders as $holderId => $holderName): ?>
                <option value="<?= (int) $holderId ?>"
                    <?= (int) ($filters['holder_id'] ?? 0) === (int) $holderId ? 'selected' : '' ?>>
                    <?= $e($holderName) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group mb-0">
        <label class="form-label u-fs-072 u-vis-hidden">Actions</label>
        <div class="d-flex gap-2 flex-wrap">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter"></i> Apply
            </button>
            <a href="/badges" class="btn btn-secondary">
                <i class="fa-solid fa-xmark"></i> Clear
            </a>
        </div>
    </div>

</form>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => (int) ($meta['total'] ?? count($badges)),
        'noun'  => 'badge',
        'query' => $search,
    ]); ?>

    <?php if (empty($badges)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-id-badge"></i>
            <div class="empty-state-title">
                <?= $search !== '' || !empty(array_filter($filters))
                    ? 'No matching badges'
                    : 'No badges issued yet' ?>
            </div>
            <div class="empty-state-text">
                Badges are issued against a checked-in visit and closed out when the card comes back.
            </div>
            <?php if ($canIssue): ?>
                <a href="/badges/create" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Issue Badge
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Badge</th>
                        <th>Holder</th>
                        <th>Visit</th>
                        <th>Gatepass</th>
                        <th>Issued</th>
                        <th>Returned</th>
                        <th>Status</th>
                        <?php if ($canIssue || $canReturn): ?>
                            <th class="text-end" width="150">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($badges as $badge): ?>
                        <?php
                            $visitId      = (int) ($badge['visit_id'] ?? 0);
                            $holderId     = (int) ($badge['holder_id'] ?? 0);
                            $gatepassId   = (int) ($badge['gatepass_id'] ?? 0);
                            $isReturned   = !empty($badge['returned_at']);
                            $isIssued     = !$isReturned && (int) ($badge['is_active'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td><span class="cell-mono"><?= $e($badge['badge_code']) ?></span></td>

                            <td>
                                <?php if ($holderId > 0 && can('visitors.view')): ?>
                                    <a class="cell-main" href="/visitors/<?= $holderId ?>"><?= $e($badge['holder_name']) ?></a>
                                <?php else: ?>
                                    <span class="cell-main"><?= $e($badge['holder_name']) ?></span>
                                <?php endif; ?>
                                <span class="cell-sub"><?= $e($badge['company_name'] ?? $badge['department_name'] ?? '—') ?></span>
                            </td>

                            <td>
                                <span class="cell-sub u-mt-0">#<?= $visitId ?></span>
                                <span class="cell-date"><?= $e($badge['purpose'] ?? '—') ?></span>
                            </td>

                            <td>
                                <?php if ($gatepassId > 0 && can('gatepass.view')): ?>
                                    <a href="/gatepasses/<?= $gatepassId ?>"><?= $e($badge['gatepass_number']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted"><?= $gatepassId > 0 ? $e($badge['gatepass_number']) : '—' ?></span>
                                <?php endif; ?>
                            </td>

                            <td class="cell-date"><?= format_date($badge['issued_at'] ?? null) ?></td>

                            <td class="cell-date"><?= format_date($badge['returned_at'] ?? null) ?></td>

                            <td>
                                <?php if ($isReturned): ?>
                                    <span class="badge badge-returned">Returned</span>
                                <?php elseif ($isIssued): ?>
                                    <span class="badge badge-warning">Issued</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Closed</span>
                                <?php endif; ?>
                            </td>

                            <?php if ($canIssue || $canReturn): ?>
                                <td class="table-actions justify-content-end">
                                    <?php if ($isIssued && $canReturn): ?>
                                        <form method="POST" action="/badges/<?= $visitId ?>/return" class="table-action-button"
                                              data-confirm="Record this badge as returned?">
                                            <input type="hidden" name="_token" value="<?= $e($token) ?>">
                                            <input type="hidden" name="redirect_to" value="/badges">
                                            <button class="btn btn-sm btn-secondary">Return</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted u-fs-075">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ((int) ($meta['last_page'] ?? 1) > 1): ?>
            <div class="table-card-foot">
                <span class="text-muted u-fs-078">
                    Showing <?= (int) ($meta['from'] ?? 1) ?>&ndash;<?= (int) ($meta['to'] ?? 0) ?>
                    of <?= (int) ($meta['total'] ?? 0) ?>
                </span>

                <nav class="pagination" aria-label="Pagination">
                    <?php if ((int) $meta['current_page'] > 1): ?>
                        <a class="page-link" href="<?= $pageUrl((int) $meta['current_page'] - 1) ?>" aria-label="Previous page">&laquo;</a>
                    <?php endif; ?>

                    <span class="page-info">
                        Page <strong><?= (int) $meta['current_page'] ?></strong> of <?= (int) $meta['last_page'] ?>
                    </span>

                    <?php if ((int) $meta['current_page'] < (int) $meta['last_page']): ?>
                        <a class="page-link" href="<?= $pageUrl((int) $meta['current_page'] + 1) ?>" aria-label="Next page">&raquo;</a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
