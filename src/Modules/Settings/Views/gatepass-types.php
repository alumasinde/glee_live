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
                    $checkin  = (bool)($actions['checkin'] ?? false);
                    $checkout = (bool)($actions['checkout'] ?? false);
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
                                    '<?= htmlspecialchars(addslashes($type->name)) ?>'
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
            <button class="btn btn-primary" onclick="save()">Save</button>
        </div>

    </div>
</div>

<script>
let currentId = null;

function openEditModal(id, checkin, checkout, name) {
    currentId = id;

    document.getElementById('modalTitle').innerText = "Configure: " + name;
    document.getElementById('checkin').checked = checkin;
    document.getElementById('checkout').checked = checkout;

    document.getElementById('modal').hidden = false;
}

function closeModal() {
    document.getElementById('modal').hidden = true;
    currentId = null;
}

async function save() {
    if (!currentId) return;

    const btn = event.target;
    btn.disabled = true;
    btn.innerText = "Saving...";

    try {
        const res = await apiPut('/settings/gatepass-types/update', {
            id: currentId,
            checkin: document.getElementById('checkin').checked ? 1 : 0,
            checkout: document.getElementById('checkout').checked ? 1 : 0,
        });

        if (!res.ok) throw new Error("Update failed");

        location.reload(); // refresh table with latest values

    } catch (e) {
        showToast(e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerText = "Save";
    }
}
</script>