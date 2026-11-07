/* Gatepass type configuration modal save. */
(function () {
    'use strict';

    var modal = document.getElementById('modal');
    var save = document.getElementById('gt-save');

    if (!modal || !save) return;

    function value(id) {
        var el = document.getElementById(id);
        return el && el.checked ? 1 : 0;
    }

    save.addEventListener('click', function () {
        var idField = modal.querySelector('[name="id"]');
        var id = idField ? idField.value : '';

        if (!id || save.disabled) return;

        save.disabled = true;
        save.textContent = 'Saving…';

        fetch('/settings/gatepass-types/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.GP.csrf()
            },
            body: JSON.stringify({
                id: parseInt(id, 10),
                checkin: value('checkin'),
                checkout: value('checkout'),
                return: value('return'),
                returnable: value('returnable')
            })
        })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (data) {
                    if (!res.ok) throw new Error(data.message || 'Update failed');
                    return data;
                });
            })
            .then(function () {
                window.showToast('Saved successfully', 'success');
                setTimeout(function () { window.location.reload(); }, 800);
            })
            .catch(function (err) {
                window.showToast(err.message || 'Network error', 'error');
            })
            .finally(function () {
                save.disabled = false;
                save.textContent = 'Save';
            });
    });
})();
