<aside class="sidebar" id="sidebar">

    <?php
        $current ??= parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        $tenantLogo = $_SESSION['user']['tenant_logo'] ?? '';
        $logoPath   = $tenantLogo !== ''
            ? base_path('public/uploads/tenants/' . basename($tenantLogo))
            : null;
    ?>

    <div class="brand">
        <?php if ($logoPath && is_file($logoPath)): ?>
            <img class="brand-logo"
                 src="/uploads/tenants/<?= htmlspecialchars(basename($tenantLogo)) ?>"
                 alt="<?= htmlspecialchars($_SESSION['user']['tenant_name'] ?? 'Company logo') ?>">
        <?php else: ?>
            <i class="fa-solid fa-id-badge"></i>
        <?php endif; ?>
        <span>
            <?= htmlspecialchars($_SESSION['user']['tenant_code'] ?? '') ?>
        </span>
    </div>

    <ul class="menu">

        <?php foreach (config('menu') as $item): ?>
            <?php if (!can($item['permission'])) {
                continue;
            } ?>
            <?php
                $active = $item['match'] === 'prefix'
                    ? str_starts_with($current, $item['route'])
                    : $current === $item['route'];
            ?>
            <li>
                <a href="<?= $item['route'] ?>" class="<?= $active ? 'active' : '' ?>">
                    <i class="<?= $item['icon'] ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                </a>
            </li>
        <?php endforeach; ?>

    </ul>

</aside>
