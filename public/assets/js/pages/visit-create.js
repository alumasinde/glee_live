/* Create-visit form: host selection drives the department select. */
(function () {
    'use strict';

    var hostSelect = document.getElementById('hostSelect');
    var deptSelect = document.getElementById('departmentSelect');

    if (!hostSelect || !deptSelect) return;

    function syncDepartment() {
        var selected = hostSelect.options[hostSelect.selectedIndex];
        if (!selected) return;

        var departmentId = selected.getAttribute('data-department');

        if (departmentId) {
            deptSelect.value = departmentId;
        }
    }

    hostSelect.addEventListener('change', syncDepartment);

    // Important after a validation reload.
    if (hostSelect.value) {
        syncDepartment();
    }
})();
