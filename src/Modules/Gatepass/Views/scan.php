<?php
    /** @var string $error ?? undefined */
    $gates = $gates ?? [];

    $activeGates = array_filter($gates, fn ($g) => (int) $g['is_active'] === 1);

    $selectedGate = (int) ($selected_gate ?? 0);
    $selectedValid = false;

    foreach ($activeGates as $g) {
        if ((int) $g['id'] === $selectedGate) {
            $selectedValid = true;
            break;
        }
    }
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-qrcode"></i> Scan Gatepass
    </h1>

    <div class="page-actions">
        <a href="/gatepasses" class="btn btn-secondary">
            <i class="fa-solid fa-arrow-left"></i> Back to Gatepasses
        </a>
    </div>
</div>

<div class="gp-card u-maxw-520">

    <?php if (!empty($error)): ?>
        <div class="gp-alert gp-alert--danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/gatepasses/scan">
        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

        <?php if ($activeGates !== []): ?>
            <div class="gp-field">
                <label class="gp-label" for="gate_id">Gate</label>
                <select class="gp-input" id="gate_id" name="gate_id">
                    <option value="0">No gate (general scan)</option>
                    <?php foreach ($activeGates as $g): ?>
                        <option value="<?= (int) $g['id'] ?>"
                            <?= $selectedValid && (int) $g['id'] === $selectedGate ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['name']) ?> (<?= htmlspecialchars($g['code']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="gp-hint">Scans are attributed to this gate for occupancy and audit.</span>
            </div>
        <?php else: ?>
            <div class="gp-hint u-mb-12">
                No gates configured yet — add them under Settings → Gates to track scans per gate.
            </div>
        <?php endif; ?>

        <div class="gp-field">
            <label class="gp-label" for="gatepass_number">
                Gatepass Number / QR
            </label>
            <input
                class="gp-input"
                type="text"
                id="gatepass_number"
                name="gatepass_number"
                placeholder="GP-000001 or scan result"
                autofocus
                autocomplete="off"
                required>
            <span class="gp-hint">
                Point the camera at a QR pass, paste the code from the QR sticker, or type the gatepass number.
            </span>
        </div>

        <div id="qr-reader" class="u-d-none u-maxw-400 u-mt-0 u-mx-auto u-mb-12"></div>

        <div class="gp-actions">
            <button type="submit" class="gp-btn gp-btn--primary">
                <i class="fa-solid fa-circle-check"></i> Process Scan
            </button>

            <button type="button" class="gp-btn" id="btn-camera-scan">
                <i class="fa-solid fa-camera"></i> Start Camera
            </button>
            <button type="button" class="gp-btn u-d-none" id="btn-camera-stop">
                <i class="fa-solid fa-camera"></i> Stop Camera
            </button>
        </div>
    </form>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="/assets/js/pages/scan.js" defer></script>
