<form method="GET" class="report-filters">

    <div class="form-group" style="margin-bottom:0">
        <label class="form-label" style="font-size:0.72rem">Search</label>
        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
            placeholder="Search…"
            style="min-width:220px"
        >
    </div>

    <?php if (!empty($filters ?? [])): ?>
        <?php foreach ($filters as $name => $options): ?>
            <div class="form-group" style="margin-bottom:0">
                <label class="form-label" style="font-size:0.72rem">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $name))) ?>
                </label>

                <select name="filters[<?= htmlspecialchars($name) ?>]" style="min-width:160px">
                    <option value="">All</option>

                    <?php foreach ($options as $value => $label): ?>
                        <option
                            value="<?= htmlspecialchars($value) ?>"
                            <?= ($_GET['filters'][$name] ?? '') == $value ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="form-group" style="margin-bottom:0">
        <label class="form-label" style="font-size:0.72rem; visibility:hidden">Actions</label>
        <div style="display:flex; gap:var(--space-2); flex-wrap:wrap">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-filter"></i> Apply
            </button>

            <?php if (!empty($export_perm ?? '') && can($export_perm)): ?>
                <?php
                    $exportPath  = strtok($_SERVER['REQUEST_URI'], '?') . '/export';
                    $exportQuery = $_SERVER['QUERY_STRING'] ?? '';
                    $exportUrl   = $exportQuery !== '' ? $exportPath . '?' . $exportQuery : $exportPath;
                ?>
                <a href="<?= htmlspecialchars($exportUrl) ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-file-csv"></i> Export CSV
                </a>
            <?php endif; ?>

            <a href="<?= htmlspecialchars(strtok($_SERVER['REQUEST_URI'], '?')) ?>" class="btn btn-secondary">
                <i class="fa-solid fa-xmark"></i> Clear
            </a>
        </div>
    </div>

</form>
