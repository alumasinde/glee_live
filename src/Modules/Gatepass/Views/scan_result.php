<?php /** @var string $message */ ?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-circle-check"></i> Scan Result
    </h1>
</div>

<div class="gp-card" style="max-width: 520px;">
    <div class="gp-section-header">
        <h2 class="gp-section-title"><?= htmlspecialchars($message) ?></h2>
        <?php if (!empty($gate)): ?>
            <p class="gp-hint">
                <i class="fa-solid fa-door-open"></i>
                <?= htmlspecialchars($gate) ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="gp-actions">
        <a href="/gatepasses/scan" class="gp-btn gp-btn--primary">
            <i class="fa-solid fa-qrcode"></i> Scan Next
        </a>
        <a href="/gatepasses" class="gp-btn gp-btn--secondary">
            View Gatepasses
        </a>
    </div>
</div>
