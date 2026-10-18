<?php
$title = $title ?? 'GPMS Dashboard';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>

    <!-- Main CSS -->
  <link rel="stylesheet" href="/assets/css/app.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/app.css'); ?>">


    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>
<body>

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

        <?php if ($message !== ''): ?>
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

<script>
document.addEventListener("DOMContentLoaded", function () {

    const toggleBtn = document.getElementById("sidebarToggle");
    const sidebar = document.getElementById("sidebar");

    toggleBtn.addEventListener("click", function () {

        // Mobile behavior
        if (window.innerWidth <= 768) {
            sidebar.classList.toggle("open");
        }
        // Desktop collapse behavior
        else {
            document.body.classList.toggle("sidebar-collapsed");
        }

    });

});

document.addEventListener("DOMContentLoaded", function () {
    const alerts = document.querySelectorAll(".auto-dismiss");

    alerts.forEach(function(alert) {

        // Auto dismiss after 4 seconds
        setTimeout(function () {
            alert.classList.add("hide");

            // Remove from DOM after animation completes
            setTimeout(function () {
                alert.remove();
            }, 400); // matches CSS transition time

        }, 4000);
    });
});

// Modal auto-dismiss functionality
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('show');
        const autoDismiss = parseInt(modal.dataset.autoDismiss || 0);
        if (autoDismiss > 0) {
            setTimeout(function() {
                closeModal(id);
            }, autoDismiss * 1000);
        }
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hide');
        setTimeout(function() {
            modal.classList.remove('show', 'hide');
        }, 300);
    }
}

// Close modal on backdrop click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal(e.target.id);
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.querySelector('.modal.show');
        if (modal) {
            closeModal(modal.id);
        }
    }
});
</script>


</body>
</html>
