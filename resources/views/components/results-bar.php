<?php
/**
 * Results bar shown at the top of a table-card.
 *
 * Variables:
 *   $total    int     Row count (or filtered total).
 *   $noun     string  Singular label, e.g. 'gatepass'.
 *   $query    string  (optional) Active search term; defaults to $_GET q/search.
 *   $clearUrl string  (optional) URL that resets the search; defaults to current path.
 */

$total = (int) ($total ?? 0);
$noun  = htmlspecialchars($noun ?? 'record', ENT_QUOTES, 'UTF-8');

$query = $query ?? trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
$query = (string) $query;

$clearUrl = $clearUrl ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$hasFilters = !empty(array_intersect_key($_GET, array_flip(['filters', 'from', 'to', 'status'])));

// Pluralise: gatepass → gatepasses, visit → visits, audit entry → audit entries
$plural = match (true) {
    str_ends_with($noun, 'ss') => $noun . 'es',
    str_ends_with($noun, 's')  => $noun,
    str_ends_with($noun, 'y')  => substr($noun, 0, -1) . 'ies',
    default                    => $noun . 's',
};
$label = $total === 1 ? $noun : $plural;
?>
<div class="results-bar">
    <span class="results-count">
        <?php if ($query !== ''): ?>
            <strong><?= $total ?></strong> <?= $label ?> matching “<?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>”
        <?php else: ?>
            <strong><?= $total ?></strong> <?= $label ?>
        <?php endif; ?>
    </span>

    <?php if ($query !== '' || $hasFilters): ?>
        <span class="results-active">
            <?php if ($query !== ''): ?>
                <span class="chip">
                    <i class="fa-solid fa-magnifying-glass" style="font-size:0.65rem"></i>
                    <?= htmlspecialchars($query, ENT_QUOTES, 'UTF-8') ?>
                </span>
            <?php endif; ?>
            <a class="back-link" style="margin:0" href="<?= htmlspecialchars((string) $clearUrl, ENT_QUOTES, 'UTF-8') ?>">
                <i class="fa-solid fa-xmark"></i> Clear
            </a>
        </span>
    <?php endif; ?>
</div>
