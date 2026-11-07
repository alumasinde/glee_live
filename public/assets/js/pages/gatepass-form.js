/* Create/Edit gatepass: repeatable item rows and the returnable/date coupling. */
(function () {
    'use strict';

    var wrapper = document.getElementById('items-wrapper');
    if (!wrapper) return;

    var itemIndex = parseInt(wrapper.getAttribute('data-next-index') || '0', 10);

    function rowMarkup(index) {
        return '' +
            '<div class="gp-item__fields">' +
            '  <div class="gp-field gp-field--grow-2">' +
            '    <label class="gp-label">Item Name <span class="gp-required">*</span></label>' +
            '    <input class="gp-input" type="text" name="items[' + index + '][item_name]" required>' +
            '  </div>' +
            '  <div class="gp-field gp-field--grow-2">' +
            '    <label class="gp-label">Description</label>' +
            '    <input class="gp-input" type="text" name="items[' + index + '][description]">' +
            '  </div>' +
            '  <div class="gp-field gp-field--shrink">' +
            '    <label class="gp-label">Qty</label>' +
            '    <input class="gp-input" type="number" name="items[' + index + '][quantity]" value="1" min="1">' +
            '  </div>' +
            '  <div class="gp-field gp-field--grow-1">' +
            '    <label class="gp-label">Serial No.</label>' +
            '    <input class="gp-input" type="text" name="items[' + index + '][serial_number]">' +
            '  </div>' +
            '</div>' +
            '<div class="gp-item__footer">' +
            '  <input type="hidden" name="items[' + index + '][is_returnable]" value="0">' +
            '  <label class="gp-checkbox">' +
            '    <input type="checkbox" name="items[' + index + '][is_returnable]" value="1">' +
            '    <span class="gp-checkbox__box"></span>' +
            '    <span class="gp-checkbox__label">Returnable Item</span>' +
            '  </label>' +
            '  <button type="button" class="gp-btn gp-btn--danger gp-btn--sm" data-remove-item>' +
            '    <i class="fa-solid fa-trash-can"></i> Remove' +
            '  </button>' +
            '</div>';
    }

    var addBtn = document.getElementById('add-item');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var row = document.createElement('div');
            row.classList.add('gp-item');
            row.innerHTML = rowMarkup(itemIndex);
            wrapper.appendChild(row);
            itemIndex++;
        });
    }

    wrapper.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-remove-item]');
        if (!btn) return;

        var row = btn.closest('.gp-item');
        if (row) row.remove();
    });

    /* Type decides the starting Returnable value; the requester may still change it. */
    var typeSelect = document.querySelector('select[name="gatepass_type_id"]');
    var returnable = document.getElementById('is_returnable');
    var returnDate = document.getElementById('expected_return_date');
    var hint = document.getElementById('return-date-hint');

    if (!typeSelect || !returnable || !returnDate) return;

    var overridden = wrapper.getAttribute('data-returnable-overridden') === '1';

    function typeDefault() {
        var opt = typeSelect.selectedOptions[0];
        return !!opt && opt.dataset.returnable === '1';
    }

    function syncDateRequirement() {
        returnDate.required = returnable.checked;

        if (hint) {
            hint.textContent = returnable.checked ? '(Required for a returnable pass)' : '(Optional)';
        }
    }

    typeSelect.addEventListener('change', function () {
        if (!overridden) returnable.checked = typeDefault();
        syncDateRequirement();
    });

    returnable.addEventListener('change', function () {
        overridden = true;
        syncDateRequirement();
    });

    if (!overridden && typeSelect.value) returnable.checked = typeDefault();
    syncDateRequirement();
})();
