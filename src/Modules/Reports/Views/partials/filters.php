<form method="GET" class="report-filters">

    <input 
        type="text" 
        name="search" 
        value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" 
        placeholder="Search..."
    >

    <?php if (!empty($filters ?? [])): ?>
        <?php foreach ($filters as $name => $options): ?>
            <select name="filters[<?= $name ?>]">
                <option value="">All <?= ucfirst($name) ?></option>
                <?php foreach ($options as $value => $label): ?>
                    <option 
                        value="<?= $value ?>"
                        <?= ($_GET['filters'][$name] ?? '') == $value ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endforeach; ?>
    <?php endif; ?>

    <button type="submit">Filter</button>
</form>