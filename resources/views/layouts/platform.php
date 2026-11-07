<?php
$title = $title ?? 'Platform console';
$platformUser = \App\Modules\Platform\Services\PlatformAuthService::user();
$navPath = '/' . trim((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="csrf-token" content="<?= \App\Middleware\CSRFMiddleware::token() ?>">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/app.css') ?>">
    <link rel="stylesheet" href="/assets/css/platform.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/platform.css') ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="/assets/js/app.js" defer></script>
</head>

<body class="platform-body">

    <header class="platform-bar">
        <div class="platform-brand">
            <i class="fa-solid fa-shield-halved"></i>
            <span>Platform console</span>
        </div>

        <?php if ($platformUser !== null): ?>
            <nav class="platform-nav">
                <a href="/platform" class="<?= $navPath === '/platform' ? 'active' : '' ?>">Tenants</a>
                <a href="/platform/features" class="<?= str_starts_with($navPath, '/platform/features') ? 'active' : '' ?>">Features &amp; permissions</a>
                <a href="/platform/audit" class="<?= str_starts_with($navPath, '/platform/audit') ? 'active' : '' ?>">Audit</a>
            </nav>

            <div class="platform-actor">
                <span><?= htmlspecialchars((string) $platformUser['name']) ?></span>
                <form method="POST" action="/platform/logout">
                    <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                    <button type="submit" class="btn btn-secondary">Sign out</button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php $platformSupport = \App\Modules\Platform\Services\PlatformImpersonationService::active(); ?>

    <?php if ($platformSupport !== null): ?>
        <div class="platform-support-bar">
            <span>
                Support session active for
                <strong><?= htmlspecialchars((string) ($_SESSION['user']['tenant_name'] ?? 'this tenant')) ?></strong>.
                Return to end it before starting another.
            </span>

            <form method="POST" action="/platform/impersonation/stop">
                <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                <button type="submit" class="btn btn-secondary">Return to platform</button>
            </form>
        </div>
    <?php endif; ?>

    <main class="platform-main">

        <?php if (!empty($_SESSION['flash'])): ?>
            <?php
            $type    = $_SESSION['flash']['type'] ?? 'info';
            $message = $_SESSION['flash']['message'] ?? '';
            unset($_SESSION['flash']);
            ?>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= htmlspecialchars($type) ?> auto-dismiss">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?= $content ?>

    </main>

</body>

</html>
