<?php if (($data['meta']['last_page'] ?? 1) > 1): ?>
    <?php
        $meta  = $data['meta'];
        $links = [];

        if ($meta['current_page'] > 1) {
            $q = $_GET;
            $q['page'] = $meta['current_page'] - 1;
            $links[] = '<a class="page-link" href="?' . htmlspecialchars(http_build_query($q), ENT_QUOTES) . '" aria-label="Previous page">&laquo;</a>';
        }

        $links[] = '<span class="page-info">Page <strong>' . (int) $meta['current_page'] . '</strong> of ' . (int) $meta['last_page'] . '</span>';

        if ($meta['current_page'] < $meta['last_page']) {
            $q = $_GET;
            $q['page'] = $meta['current_page'] + 1;
            $links[] = '<a class="page-link" href="?' . htmlspecialchars(http_build_query($q), ENT_QUOTES) . '" aria-label="Next page">&raquo;</a>';
        }
    ?>
    <nav class="pagination" aria-label="Pagination">
        <?= implode('', $links) ?>
    </nav>
<?php endif; ?>
