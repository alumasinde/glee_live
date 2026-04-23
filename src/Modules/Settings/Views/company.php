<h1 class="page-heading">
    <i class="fa-solid fa-building"></i> Company Settings
</h1>

<div class="form-card">

    <form method="POST" action="/settings/company">

        <div class="form-group">
            <label class="form-label">Company Name</label>
            <input type="text"
                   name="company_name"
                   class="form-control"
                   value="<?= htmlspecialchars($company['name'] ?? '') ?>"
                   required>
        </div>

        <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email"
                   name="email"
                   class="form-control"
                   value="<?= htmlspecialchars($company['email'] ?? '') ?>">
        </div>

         <div class="form-group">
            <label class="form-label">Company Code</label>
            <input type="text"
                   name="code"
                   class="form-control"
                   value="<?= htmlspecialchars($company['code'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text"
                   name="phone"
                   class="form-control"
                   value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Country</label>
            <input type="text"
                   name="country"
                   class="form-control"
                   value="<?= htmlspecialchars($company['country'] ?? '') ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-save"></i> Save Settings
            </button>

            <a href="/settings" class="btn btn-secondary">
            Back
        </a>
        </div>
        

    </form>

</div>