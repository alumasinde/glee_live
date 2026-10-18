<?php use App\Core\View; ?>

<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-id-badge"></i> Gatepass Types
    </h1>
    <div class="page-actions">
        <a href="/settings" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Settings
        </a>
    </div>
</div>

<div class="table-card">
    <?php View::render('components/results-bar', [
        'total' => count($types),
        'noun'  => 'gatepass type',
    ]); ?>

    <?php if (empty($types)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-id-badge"></i>
            <div class="empty-state-title">No gatepass types found</div>
            <div class="empty-state-text">
                Types define which actions — check-in, check-out, returnable — a gatepass permits.
            </div>
        </div>
    <?php else: ?>

        <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Returnable</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($types as $type):
                    $actions = $type->allowedActions ?? ['checkin' => false, 'checkout' => false];
                    $checkin  = !empty($actions['checkin']);
                    $checkout = !empty($actions['checkout']);

                    // SAFE JS encoding (important fix)
                    $safeName = htmlspecialchars(json_encode($type->name), ENT_QUOTES, 'UTF-8');
                ?>
                    <tr data-id="<?= (int)$type->id ?>">

                        <td><strong><?= htmlspecialchars($type->name) ?></strong></td>
                        <td><code><?= htmlspecialchars($type->code ?? '—') ?></code></td>

                        <td>
                            <?= $checkin
                                ? '<span class="badge badge-success">Enabled</span>'
                                : '<span class="badge badge-secondary">Disabled</span>' ?>
                        </td>

                        <td>
                            <?= $checkout
                                ? '<span class="badge badge-success">Enabled</span>'
                                : '<span class="badge badge-secondary">Disabled</span>' ?>
                        </td>

                        <td>
                            <?= !empty($type->isReturnable)
                                ? '<span class="badge badge-info">Yes</span>'
                                : '<span class="badge badge-secondary">No</span>' ?>
                        </td>

                        <td style="text-align:right;">
                            <button
                                class="btn btn-secondary btn-sm"
                                onclick="openEditModal(
                                    <?= (int)$type->id ?>,
                                    <?= $checkin ? 'true' : 'false' ?>,
                                    <?= $checkout ? 'true' : 'false' ?>,
                                    <?= $safeName ?>
                                )"
                            >
                                Configure
                            </button>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

    <?php endif; ?>
</div>

<!-- MODAL -->
<div id="modal" class="modal">
    <div class="modal-content">

        <div class="modal-header">
            <h3 id="modalTitle">Configure</h3>
            <button type="button" onclick="closeModal()" aria-label="Close">&times;</button>
        </div>

        <div class="modal-body">
            <div class="form-group">
                <label>
                    <input type="checkbox" id="checkin">
                    Allow Check-in
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="checkout">
                    Allow Check-out
                </label>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="save(this)">Save</button>
        </div>

    </div>
</div>

<script>
let currentId = null;

/* ---------------- MODAL ---------------- */
function openEditModal(id, checkin, checkout, name) {
    currentId = id;

    document.getElementById('modalTitle').innerText = "Configure: " + name;

    document.getElementById('checkin').checked = !!checkin;
    document.getElementById('checkout').checked = !!checkout;

    document.getElementById('modal').classList.add('show');
}

function closeModal() {
    document.getElementById('modal').classList.remove('show');
    currentId = null;
}

document.getElementById('modal').addEventListener('click', function (e) {
    if (e.target === this) closeModal();
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
});

/* ---------------- API ---------------- */
async function apiPost(url, data) {
    return fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-Token": "<?= \App\Middleware\CSRFMiddleware::token() ?>"
        },
        body: JSON.stringify(data)
    });
}

/* ---------------- TOAST ---------------- */
function showToast(message, type = 'info') {
    const toast = document.createElement("div");
    toast.innerText = message;

    toast.style.position = "fixed";
    toast.style.bottom = "20px";
    toast.style.right = "20px";
    toast.style.padding = "10px 15px";
    toast.style.borderRadius = "6px";
    toast.style.color = "#fff";
    toast.style.zIndex = "9999";
    toast.style.fontSize = "14px";
    toast.style.maxWidth = "300px";

    if (type === 'error') {
        toast.style.background = "#dc3545";
    } else if (type === 'success') {
        toast.style.background = "#28a745";
    } else {
        toast.style.background = "#333";
    }

    document.body.appendChild(toast);

    setTimeout(() => toast.remove(), 3000);
}

/* ---------------- SAVE ---------------- */
async function save(btn) {
    if (!currentId || btn.disabled) return;

    btn.disabled = true;
    btn.innerText = "Saving...";

    try {
        const res = await apiPost('/settings/gatepass-types/update', {
            id: currentId,
            checkin: document.getElementById('checkin').checked ? 1 : 0,
            checkout: document.getElementById('checkout').checked ? 1 : 0,
        });

        let data = {};
        try {
            data = await res.json();
        } catch (e) {}

        if (!res.ok) {
            throw new Error(data.message || "Update failed");
        }

        showToast("Saved successfully", "success");

        setTimeout(() => location.reload(), 800);

    } catch (e) {
        console.error(e);
        showToast(e.message || "Network error", "error");
    } finally {
        btn.disabled = false;
        btn.innerText = "Save";
    }
}
</script>