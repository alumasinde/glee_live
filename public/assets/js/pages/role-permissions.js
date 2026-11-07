/* Role permission matrix: per-module toggle-all and expand/collapse. */
(function () {
    'use strict';

    document.querySelectorAll('.toggle-module').forEach(function (toggle) {
        var moduleClass = 'module-' + toggle.dataset.module;
        var checkboxes = document.querySelectorAll('.' + moduleClass);

        function updateState() {
            var total = checkboxes.length;
            var checked = Array.from(checkboxes).filter(function (cb) { return cb.checked; }).length;

            toggle.checked = total > 0 && checked === total;
            toggle.indeterminate = checked > 0 && checked < total;
        }

        updateState();

        toggle.addEventListener('change', function () {
            checkboxes.forEach(function (cb) { cb.checked = toggle.checked; });
        });

        checkboxes.forEach(function (cb) {
            cb.addEventListener('change', updateState);
        });
    });

    document.querySelectorAll('.card-header[data-module]').forEach(function (header) {
        header.addEventListener('click', function (e) {
            if (e.target.tagName === 'INPUT') return;

            var body = document.querySelector('.module-' + header.dataset.module);
            if (body) body.classList.toggle('expanded');
        });
    });
})();
