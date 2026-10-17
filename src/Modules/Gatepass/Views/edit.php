<h1 class="gp-heading">Edit Gatepass</h1>

<?php if (!empty($error)): ?>
    <div class="gp-alert gp-alert--danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="gp-card">
    <form method="POST" action="/gatepasses/<?= (int)$gatepass['id'] ?>">

        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

        <!-- PURPOSE -->
        <div class="gp-field">
            <label class="gp-label">Purpose <span class="gp-required">*</span></label>
            <textarea class="gp-textarea" name="purpose" required><?= htmlspecialchars(
                $_POST['purpose'] ?? $gatepass['purpose'] ?? ''
            ) ?></textarea>
        </div>

        <!-- TYPE & STATUS -->
        <div class="gp-row">
            <div class="gp-field">
                <label class="gp-label">Gatepass Type <span class="gp-required">*</span></label>
                <select class="gp-select" name="gatepass_type_id" required>
                    <option value="">— Select Type —</option>
                    <?php foreach ($gatepassTypes as $type): ?>
                        <option value="<?= (int)$type['id'] ?>"
                            <?= ((int)($gatepass['gatepass_type_id'] ?? 0) === (int)$type['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="gp-field">
                <label class="gp-label">Status <span class="gp-required">*</span></label>
                <select class="gp-select" name="status_id" required>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= (int)$status['id'] ?>"
                            <?= ((int)($gatepass['status_id'] ?? 0) === (int)$status['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- EXPECTED RETURN DATE -->
        <div class="gp-field">
            <label class="gp-label">Expected Return Date <span class="gp-hint">(Optional)</span></label>
            <input class="gp-input" type="date" name="expected_return_date"
                   value="<?= !empty($gatepass['expected_return_date'])
                       ? htmlspecialchars(date('Y-m-d', strtotime($gatepass['expected_return_date'])))
                       : '' ?>">
        </div>

        <!-- OPTIONS -->
        <div class="gp-options">
            <input type="hidden" name="is_returnable" value="0">
            <label class="gp-checkbox">
                <input type="checkbox" name="is_returnable" value="1"
                    <?= !empty($gatepass['is_returnable']) ? 'checked' : '' ?>>
                <span class="gp-checkbox__box"></span>
                <span class="gp-checkbox__label">Is Returnable <span class="gp-hint">(Gatepass Level)</span></span>
            </label>

            <input type="hidden" name="needs_approval" value="0">
            <label class="gp-checkbox">
                <input type="checkbox" name="needs_approval" value="1"
                    <?= !empty($gatepass['needs_approval']) ? 'checked' : '' ?>>
                <span class="gp-checkbox__box"></span>
                <span class="gp-checkbox__label">Needs Approval</span>
            </label>
        </div>

        <div class="gp-divider"></div>

        <!-- ITEMS -->
        <div class="gp-section-header">
            <h3 class="gp-section-title">Items</h3>
            <button type="button" class="gp-btn gp-btn--outline" onclick="addItem()">
                <i class="fa-solid fa-plus"></i> Add Item
            </button>
        </div>

        <div id="items-wrapper">
            <?php foreach ($items ?? [] as $index => $item): ?>
                <div class="gp-item">
                    <input type="hidden" name="items[<?= $index ?>][id]" value="<?= (int)$item['id'] ?>">

                    <div class="gp-item__fields">
                        <div class="gp-field gp-field--grow-2">
                            <label class="gp-label">Item Name <span class="gp-required">*</span></label>
                            <input class="gp-input" type="text"
                                   name="items[<?= $index ?>][item_name]"
                                   value="<?= htmlspecialchars($item['item_name'] ?? '') ?>" required>
                        </div>

                        <div class="gp-field gp-field--grow-2">
                            <label class="gp-label">Description</label>
                            <input class="gp-input" type="text"
                                   name="items[<?= $index ?>][description]"
                                   value="<?= htmlspecialchars($item['description'] ?? '') ?>">
                        </div>

                        <div class="gp-field gp-field--shrink">
                            <label class="gp-label">Qty</label>
                            <input class="gp-input" type="number"
                                   name="items[<?= $index ?>][quantity]"
                                   value="<?= (int)($item['quantity'] ?? 1) ?>" min="1">
                        </div>

                        <div class="gp-field gp-field--grow-1">
                            <label class="gp-label">Serial No.</label>
                            <input class="gp-input" type="text"
                                   name="items[<?= $index ?>][serial_number]"
                                   value="<?= htmlspecialchars($item['serial_number'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="gp-item__footer">
                        <input type="hidden" name="items[<?= $index ?>][is_returnable]" value="0">
                        <label class="gp-checkbox">
                            <input type="checkbox"
                                   name="items[<?= $index ?>][is_returnable]"
                                   value="1"
                                   <?= !empty($item['is_returnable']) ? 'checked' : '' ?>>
                            <span class="gp-checkbox__box"></span>
                            <span class="gp-checkbox__label">Returnable Item</span>
                        </label>

                        <button type="button" class="gp-btn gp-btn--danger gp-btn--sm" onclick="removeItem(this)">
                            <i class="fa-solid fa-trash-can"></i> Remove
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="gp-divider"></div>

        <!-- ACTIONS -->
        <div class="gp-actions">
            <button type="submit" class="gp-btn gp-btn--primary">
                <i class="fa-solid fa-save"></i> Update Gatepass
            </button>
            <a href="/gatepasses/<?= (int)$gatepass['id'] ?>" class="gp-btn gp-btn--secondary">
                <i class="fa-solid fa-xmark"></i> Cancel
            </a>
        </div>

    </form>
</div>

<script>
let itemIndex = <?= !empty($items) ? count($items) : 0 ?>;

function addItem() {
    const wrapper = document.getElementById('items-wrapper');
    const row = document.createElement('div');
    row.classList.add('gp-item');
    row.innerHTML = `
        <div class="gp-item__fields">
            <div class="gp-field gp-field--grow-2">
                <label class="gp-label">Item Name <span class="gp-required">*</span></label>
                <input class="gp-input" type="text" name="items[${itemIndex}][item_name]" required>
            </div>
            <div class="gp-field gp-field--grow-2">
                <label class="gp-label">Description</label>
                <input class="gp-input" type="text" name="items[${itemIndex}][description]">
            </div>
            <div class="gp-field gp-field--shrink">
                <label class="gp-label">Qty</label>
                <input class="gp-input" type="number" name="items[${itemIndex}][quantity]" value="1" min="1">
            </div>
            <div class="gp-field gp-field--grow-1">
                <label class="gp-label">Serial No.</label>
                <input class="gp-input" type="text" name="items[${itemIndex}][serial_number]">
            </div>
        </div>
        <div class="gp-item__footer">
            <input type="hidden" name="items[${itemIndex}][is_returnable]" value="0">
            <label class="gp-checkbox">
                <input type="checkbox" name="items[${itemIndex}][is_returnable]" value="1">
                <span class="gp-checkbox__box"></span>
                <span class="gp-checkbox__label">Returnable Item</span>
            </label>
            <button type="button" class="gp-btn gp-btn--danger gp-btn--sm" onclick="removeItem(this)">
                <i class="fa-solid fa-trash-can"></i> Remove
            </button>
        </div>
    `;
    wrapper.appendChild(row);
    itemIndex++;
}

function removeItem(button) {
    button.closest('.gp-item').remove();
}
</script>