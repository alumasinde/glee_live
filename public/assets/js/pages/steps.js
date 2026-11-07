/* Workflow step editor: kind/scope toggles and the approver roster filter. */
(function () {
    'use strict';

    function bindForm(form) {
        var kind = form.querySelector('.js-ws-kind');
        var roleBlock = form.querySelector('[data-ws-role]');
        var people = form.querySelector('[data-ws-people]');
        var resolve = form.querySelector('[data-ws-resolve]');
        var resolveTxt = resolve ? resolve.textContent : '';
        var scope = form.querySelector('.js-ws-scope');
        var search = form.querySelector('[data-ws-filter]');

        if (!kind || !roleBlock || !people) return;

        // A roster cannot be scoped by department, so the role block hides and
        // the "resolves to" line switches to the tick counter.
        function rosterCount() {
            return form.querySelectorAll('[data-ws-people] input[type="checkbox"]:checked').length;
        }

        function syncKind() {
            var isUsers = kind.value === 'users';

            roleBlock.hidden = isUsers;
            people.hidden = !isUsers;

            form.querySelectorAll('[data-ws-role] select').forEach(function (el) {
                el.disabled = isUsers;
            });

            if (resolve) {
                resolve.textContent = isUsers ? rosterCount() + ' selected' : resolveTxt;
            }
        }

        function syncDept(select) {
            var target = document.getElementById(select.dataset.target);
            if (target) target.disabled = select.value !== 'step';
        }

        function filterPeople() {
            if (!search) return;

            var needle = search.value.trim().toLowerCase();
            var visible = 0;

            form.querySelectorAll('.ws-person').forEach(function (row) {
                var hit = needle === '' || row.dataset.wsSearch.indexOf(needle) !== -1;
                row.hidden = !hit;
                if (hit) visible++;
            });

            form.querySelector('[data-ws-count]').textContent =
                visible === 0 ? 'No match' : visible + ' shown, ' + rosterCount() + ' selected';
        }

        kind.addEventListener('change', syncKind);

        if (scope) {
            scope.addEventListener('change', function () { syncDept(scope); });
            syncDept(scope);
        }

        if (search) {
            search.addEventListener('input', filterPeople);
        }

        form.querySelectorAll('[data-ws-people] input[type="checkbox"]').forEach(function (box) {
            box.addEventListener('change', function () {
                form.querySelector('[data-ws-count]').textContent = rosterCount() + ' selected';
            });
        });

        var checkAll = form.querySelector('[data-ws-check-all]');
        if (checkAll) {
            checkAll.addEventListener('click', function () {
                form.querySelectorAll('.ws-person:not([hidden]) input').forEach(function (box) {
                    box.checked = true;
                });
                form.querySelector('[data-ws-count]').textContent = rosterCount() + ' selected';
            });
        }

        var clear = form.querySelector('[data-ws-clear]');
        if (clear) {
            clear.addEventListener('click', function () {
                form.querySelectorAll('[data-ws-people] input').forEach(function (box) {
                    box.checked = false;
                });
                form.querySelector('[data-ws-count]').textContent = '0 selected';
            });
        }

        syncKind();
        filterPeople();
    }

    document.querySelectorAll('[data-ws-form]').forEach(bindForm);
})();
