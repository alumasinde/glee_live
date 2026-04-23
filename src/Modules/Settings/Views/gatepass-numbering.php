<h2>Gatepass Number Settings</h2>

<form method="POST" action="/settings/gatepass-numbering">

    <label>Prefix</label>
    <input type="text" name="prefix"
        value="<?= htmlspecialchars($config['prefix']) ?>">

    <br><br>

    <label>
        <input type="checkbox" name="include_year"
            <?= $config['include_year'] ? 'checked' : '' ?>>
        Include Year
    </label>

    <br><br>

    <label>
        <input type="checkbox" name="include_month"
            <?= $config['include_month'] ? 'checked' : '' ?>>
        Include Month
    </label>

    <br><br>

    <label>Number Padding</label>
    <input type="number" name="padding"
        value="<?= $config['padding'] ?>">

    <br><br>

    <label>Current Sequence</label>
    <input type="number" name="sequence"
        value="<?= $config['sequence'] ?>">

    <br><br>

    <label>
        <input type="checkbox" name="reset_yearly"
            <?= $config['reset_yearly'] ? 'checked' : '' ?>>
        Reset Yearly
    </label>

    <br><br>

    <button type="submit">Save Settings</button>

<a href="/settings" class="btn btn-secondary">
            Back
        </a>
</form>