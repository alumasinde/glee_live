<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $code ?> — <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?> | TuPass</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<div class="container">
    <div class="code">
        <?= $code ?>
        <span><?= $code >= 500 ? 'Server Error' : 'Request Problem' ?></span>
    </div>

    <h2><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></h2>

    <p>
        <?php if ($code >= 500): ?>
            An unexpected error occurred on the server.
            Please try again later or contact support if the problem persists.
        <?php else: ?>
            Use Back to go where you were and try again.
        <?php endif; ?>
    </p>

    <div class="buttons">
        <a href="javascript:history.back()" class="btn btn-primary">Go Back</a>
        <a href="/dashboard" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <footer>
        © <?= date('Y') ?> TuPass — Gatepass Management System
    </footer>
</div>

</body>
</html>