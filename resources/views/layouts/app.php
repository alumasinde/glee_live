<?php
$title = $title ?? 'GPMS Dashboard';



?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <meta name="csrf-token" content="<?= \App\Middleware\CSRFMiddleware::token() ?>">

    <!-- Main CSS -->
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/app.css'); ?>">


    <!-- Font Awesome -->
    <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- Shared delegated UI behaviour; views carry no inline script. -->
    <script src="/assets/js/app.js" defer></script>
</head>

<body>

    <?php $supportSession = \App\Modules\Platform\Services\PlatformImpersonationService::active(); ?>

    <?php if ($supportSession !== null): ?>
        <div class="impersonation-banner">
            <div>
                <i class="fa-solid fa-user-shield"></i>
                <strong>Support session</strong> — viewing as
                <?= htmlspecialchars(trim((string) ($_SESSION['user']['first_name'] ?? '') . ' ' . (string) ($_SESSION['user']['last_name'] ?? ''))) ?>
                at <?= htmlspecialchars((string) ($_SESSION['user']['tenant_name'] ?? '')) ?>.
                Ends <?= htmlspecialchars((string) ($supportSession['expires_at'] ?? '')) ?>.
            </div>

            <form method="POST" action="/platform/impersonation/stop">
                <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
                <button type="submit" class="btn btn-secondary">Return to platform</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="app-container">

        <?php require base_path('resources/views/templates/sidebar.php'); ?>

        <div class="main-content">

            <?php require base_path('resources/views/templates/navbar.php'); ?>

            <div class="content-wrapper">

                <?php if (!empty($_SESSION['flash'])): ?>
                    <?php
                    $type    = $_SESSION['flash']['type'] ?? 'info';
                    $message = $_SESSION['flash']['message'] ?? '';

                    unset($_SESSION['flash']);
                    ?>

                    <?php if ($message !== '') : ?>
                        <div class="alert alert-<?= htmlspecialchars($type) ?> auto-dismiss">
                            <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>

                <?= $content ?>

            </div>

            <?php require base_path('resources/views/templates/footer.php'); ?>

        </div>

    </div>

    </body>

</html>