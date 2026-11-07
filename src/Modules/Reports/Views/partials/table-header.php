<?php
function sortLink($column, $label)
{
    $isActive = ($_GET['sort_by'] ?? '') === $column;
    $dir      = $isActive && ($_GET['sort_dir'] ?? '') === 'asc' ? 'desc' : 'asc';

    $query = $_GET;
    $query['sort_by']  = $column;
    $query['sort_dir'] = $dir;

    $url = '?' . http_build_query($query);

    $icon = '';
    if ($isActive) {
        $icon = ($_GET['sort_dir'] ?? 'asc') === 'asc'
            ? ' <i class="fa-solid fa-arrow-up-wide-short u-fs-065"></i>'
            : ' <i class="fa-solid fa-arrow-down-wide-short u-fs-065"></i>';
    }

    echo '<th><a class="sort-link' . ($isActive ? ' sort-active' : '') . '" href=\'' . htmlspecialchars($url, ENT_QUOTES) . '\'>'
        . htmlspecialchars($label) . $icon . '</a></th>';
}
