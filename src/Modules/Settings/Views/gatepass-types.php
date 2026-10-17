<h1 class="page-heading">
    <i class="fa-solid fa-id-badge"></i>
    Gatepass Types
</h1>

<div class="form-card">

    <p class="text-muted" style="margin-bottom: 1rem;">
        Configure which actions each gatepass type permits.
    </p>

    <?php if (empty($types)): ?>
        <p>No gatepass types found.</p>
    <?php else: ?>

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

    <?php endif; ?>
</div>

<!-- MODAL -->
<div id="modal" class="modal-overlay" hidden>
    <div class="modal">

        <h3 id="modalTitle">Configure</h3>

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

        <div class="form-actions">
            <button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button class="btn btn-primary" onclick="save(this)">Save</button>
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

    document.getElementById('modal').hidden = false;
}

function closeModal() {
    document.getElementById('modal').hidden = true;
    currentId = null;
}

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