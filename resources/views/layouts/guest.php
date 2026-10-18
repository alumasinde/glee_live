<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?? 'Guest' ?></title>
<link rel="stylesheet" href="/assets/css/login.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/css/login.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

    <?= $content ?>

    <script>
    // Modal auto-dismiss functionality (for guest pages with modals)
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
