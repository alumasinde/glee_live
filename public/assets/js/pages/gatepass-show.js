/* Gatepass detail: confirm-then-record check-in / check-out. */
(function () {
    'use strict';

    var page = document.querySelector('[data-gatepass-id]');
    if (!page) return;

    var gatepassId = parseInt(page.getAttribute('data-gatepass-id'), 10);
    var overlay = document.getElementById('gsOverlay');
    var confirmBtn = document.getElementById('gsConfirmBtn');
    var cancelBtn = document.getElementById('gsCancelBtn');

    if (!overlay || !confirmBtn) return;

    var pending = null;

    var CONFIG = {
        checkin: {
            icon: '⬇️',
            title: 'Confirm Check-in',
            body: 'This will record the actual check-in time as now. This cannot be undone.',
            btnClass: 'gt-btn gt-btn--checkin',
            btnLabel: 'Record Check-in',
            endpoint: '/gatepasses/' + gatepassId + '/checkin'
        },
        checkout: {
            icon: '⬆️',
            title: 'Confirm Check-out',
            body: 'This will record the actual check-out time as now. This cannot be undone.',
            btnClass: 'gt-btn gt-btn--checkout',
            btnLabel: 'Record Check-out',
            endpoint: '/gatepasses/' + gatepassId + '/checkout'
        }
    };

    function close() {
        overlay.hidden = true;
        pending = null;
    }

    document.querySelectorAll('[data-gp-action]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var action = btn.getAttribute('data-gp-action');
            var cfg = CONFIG[action];
            if (!cfg) return;

            pending = action;

            document.getElementById('gsModalIcon').textContent = cfg.icon;
            document.getElementById('gsModalTitle').textContent = cfg.title;
            document.getElementById('gsModalBody').textContent = cfg.body;

            confirmBtn.className = cfg.btnClass;
            confirmBtn.textContent = cfg.btnLabel;

            overlay.hidden = false;
            confirmBtn.focus();
        });
    });

    if (cancelBtn) cancelBtn.addEventListener('click', close);

    confirmBtn.addEventListener('click', function () {
        if (!pending) return;

        var cfg = CONFIG[pending];

        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Recording…';

        fetch(cfg.endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.GP.csrf()
            },
            body: JSON.stringify({ id: gatepassId })
        })
            .then(function (res) {
                if (!res.ok) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        throw new Error(data.message || 'Action failed. Please try again.');
                    });
                }
                return null;
            })
            .then(function () { window.location.reload(); })
            .catch(function (err) {
                window.showToast(err.message, 'error');
                close();
            })
            .finally(function () {
                confirmBtn.disabled = false;
                confirmBtn.textContent = cfg.btnLabel;
            });
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();
