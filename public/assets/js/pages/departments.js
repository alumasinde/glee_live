/* Departments: toggle and delete over fetch (no inline handlers). */
(function () {
    'use strict';

    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.GP.csrf()
            },
            body: JSON.stringify(payload)
        });
    }

    document.querySelectorAll('[data-department-toggle]').forEach(function (input) {
        input.addEventListener('change', function () {
            var id = input.getAttribute('data-id');

            post('/settings/departments/toggle', { id: parseInt(id, 10) })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (data.success) {
                        window.location.reload();
                        return;
                    }
                    window.showToast(data.message || 'Toggle failed', 'error');
                    input.checked = !input.checked;
                })
                .catch(function () {
                    window.showToast('Toggle failed', 'error');
                    input.checked = !input.checked;
                });
        });
    });

    document.querySelectorAll('[data-department-delete]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!window.confirm('Are you sure you want to delete this department?')) return;

            post('/settings/departments/delete', { id: parseInt(btn.getAttribute('data-id'), 10) })
                .then(function () { window.location.reload(); });
        });
    });
})();
