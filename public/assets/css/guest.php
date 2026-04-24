<!DOCTYPE html>
<html>
<head>
    <title><?= $title ?? 'Guest' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/assets/csss/login.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'].'/assets/csss/login.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>

    <?= $content ?>

</body>
</html>
