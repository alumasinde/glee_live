<form method="GET" class="report-filters">

    <div class="form-group mb-0">
        <label class="form-label u-fs-072">Search</label>
        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
            placeholder="Search…"
            class="u-minw-220"
        >
    </div>

    <?php if (!empty($filters ?? [])): ?>
        <?php foreach ($filters as $name => $options): ?>
            <?php
                $short = strpos($name, '.') !== false
                    ? substr($name, strrpos($name, '.') + 1)
                    : $name;
                $short = str_replace('_id', '', $short);
            ?>
            <div class="form-group mb-0">
                <label class="form-label u-fs-072">
                    <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $short))) ?>
                </label>

                <select name="filters[<?= htmlspecialchars($name) ?>]" class="u-minw-160">
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

    <div class="form-group mb-0">
        <label class="form-label u-fs-072 u-vis-hidden">Actions</label>
        <div class="d-flex gap-2 flex-wrap">
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
