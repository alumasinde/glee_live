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

<div class="gp-card" style="max-width: 520px;">

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
            <div class="gp-hint" style="margin-bottom:12px;">
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

        <div id="qr-reader" style="display:none; max-width:400px; margin:0 auto 12px;"></div>

        <div class="gp-actions">
            <button type="submit" class="gp-btn gp-btn--primary">
                <i class="fa-solid fa-circle-check"></i> Process Scan
            </button>

            <button type="button" class="gp-btn" id="btn-camera-scan">
                <i class="fa-solid fa-camera"></i> Start Camera
            </button>
            <button type="button" class="gp-btn" id="btn-camera-stop" style="display:none;">
                <i class="fa-solid fa-camera"></i> Stop Camera
            </button>
        </div>
    </form>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(function () {
    var reader = document.getElementById('qr-reader');
    var input = document.getElementById('gatepass_number');
    var form = input.closest('form');
    var startBtn = document.getElementById('btn-camera-scan');
    var stopBtn = document.getElementById('btn-camera-stop');
    var scanner = null;

    startBtn.addEventListener('click', function () {
        if (!window.Html5Qrcode) {
            alert('Camera library failed to load. Use manual entry.');
            return;
        }
        reader.style.display = 'block';
        startBtn.style.display = 'none';
        stopBtn.style.display = '';

        scanner = new Html5Qrcode('qr-reader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: 250 },
            function (decodedText) {
                scanner.stop().catch(function () {});
                reader.style.display = 'none';
                stopBtn.style.display = 'none';
                startBtn.style.display = '';
                input.value = decodedText;
                form.submit();
            },
            function () {}
        ).catch(function (err) {
            alert('Could not start camera: ' + err);
            reader.style.display = 'none';
            stopBtn.style.display = 'none';
            startBtn.style.display = '';
        });
    });

    stopBtn.addEventListener('click', function () {
        if (scanner) {
            scanner.stop().catch(function () {});
        }
        reader.style.display = 'none';
        stopBtn.style.display = 'none';
        startBtn.style.display = '';
    });
})();
</script>
