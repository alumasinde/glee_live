/*
 * Shared, delegated UI behaviour for every layout.
 *
 * Views no longer carry inline <script> blocks or on* attributes, so the
 * Content-Security-Policy can drop 'unsafe-inline' for script-src. Anything
 * page-specific lives in /assets/js/pages/*.js.
 */
(function () {
    'use strict';

    function qs(target, root) {
        return typeof target === 'string' ? (root || document).querySelector(target) : target;
    }

    window.GP = window.GP || {};

    window.GP.csrf = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    window.openModal = function (target) {
        var modal = qs(target);
        if (!modal) return;

        modal.hidden = false;

        if (modal.classList.contains('modal')) {
            modal.classList.add('show');
        } else {
            modal.style.display = 'block';
            if (typeof modal.scrollIntoView === 'function') {
                modal.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        var auto = parseInt(modal.dataset.autoDismiss || '0', 10);
        if (auto > 0) {
            setTimeout(function () { window.closeModal(modal); }, auto * 1000);
        }
    };

    window.closeModal = function (target) {
        var modal = qs(target);
        if (!modal) return;

        modal.classList.remove('show');

        if (modal.classList.contains('modal')) return; // CSS-driven visibility

        modal.style.display = 'none';
        modal.hidden = true;
    };

    window.showToast = function (message, type) {
        var toast = document.createElement('div');
        toast.textContent = message;
        toast.style.position = 'fixed';
        toast.style.bottom = '20px';
        toast.style.right = '20px';
        toast.style.padding = '10px 15px';
        toast.style.borderRadius = '6px';
        toast.style.color = '#fff';
        toast.style.zIndex = '9999';
        toast.style.fontSize = '14px';
        toast.style.maxWidth = '300px';
        toast.style.background = type === 'error' ? '#dc3545' : (type === 'success' ? '#28a745' : '#333');

        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 3000);
    };

    /* ---------------- sidebar ---------------- */

    var sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            var sidebar = document.getElementById('sidebar');
            if (!sidebar) return;

            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('open');
            } else {
                document.body.classList.toggle('sidebar-collapsed');
            }
        });
    }

    /* ---------------- flash auto-dismiss ---------------- */

    document.querySelectorAll('.auto-dismiss').forEach(function (alert) {
        setTimeout(function () {
            alert.classList.add('hide');
            setTimeout(function () { alert.remove(); }, 400);
        }, 4000);
    });

    /* ---------------- modal fill ---------------- */

    function fillForm(opener) {
        var target = qs(opener.getAttribute('data-open-form'));
        if (!target) return;

        Array.prototype.forEach.call(opener.attributes, function (attr) {
            if (attr.name.indexOf('data-field-') !== 0) return;

            var name = attr.name.slice('data-field-'.length);
            var field = target.querySelector('[name="' + name + '"]');
            if (!field) return;

            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = attr.value === '1' || attr.value === 'true';
            } else {
                field.value = attr.value;
            }
        });

        var title = opener.getAttribute('data-open-title');
        if (title !== null) {
            var slot = target.querySelector('[data-modal-title]');
            if (slot) slot.textContent = title;
        }

        window.openModal(target);

        var first = target.querySelector('input:not([type="hidden"]), select, textarea');
        if (first) first.focus();
    }

    /* ---------------- delegated interactions ---------------- */

    document.addEventListener('click', function (e) {
        var el;

        if ((el = e.target.closest('[data-confirm]'))) {
            if (!window.confirm(el.getAttribute('data-confirm'))) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
            return;
        }

        if ((el = e.target.closest('[data-close]'))) {
            e.preventDefault();
            window.closeModal(el.getAttribute('data-close'));
            return;
        }

        if ((el = e.target.closest('[data-open-form]'))) {
            e.preventDefault();
            fillForm(el);
            return;
        }

        if ((el = e.target.closest('[data-copy]'))) {
            e.preventDefault();
            var source = qs(el.getAttribute('data-copy'));
            if (source && navigator.clipboard) {
                navigator.clipboard.writeText(source.textContent.trim()).catch(function () {});
            }
            return;
        }

        if ((el = e.target.closest('[data-toggle-target]'))) {
            e.preventDefault();
            var panel = qs(el.getAttribute('data-toggle-target'));
            if (panel) panel.hidden = !panel.hidden;
            return;
        }

        if ((el = e.target.closest('.reveal-toggle')) && el.dataset.target) {
            var input = document.getElementById(el.dataset.target);
            if (!input) return;

            var showing = input.type === 'password';
            input.type = showing ? 'text' : 'password';
            el.setAttribute('aria-pressed', showing ? 'true' : 'false');
            el.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');

            var icon = el.querySelector('i');
            if (icon) icon.className = showing ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';

            input.focus();
            return;
        }

        if (e.target.classList && e.target.classList.contains('modal')) {
            window.closeModal(e.target);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;

        var open = document.querySelector('.modal.show');
        if (open) window.closeModal(open);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;

        if (form.hasAttribute('data-confirm') && !window.confirm(form.getAttribute('data-confirm'))) {
            e.preventDefault();
            return;
        }

        if (!form.classList.contains('login-card')) return;

        var btn = form.querySelector('.btn-login');
        if (!btn) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> '
            + (btn.getAttribute('data-loading-label') || 'Working…');
    });
})();
