<h1 class="page-heading">
    <i class="fa-solid fa-building"></i> Company Settings
</h1>

<div class="form-card">

    <form method="POST" action="/settings/company" enctype="multipart/form-data">

        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">

        <div class="form-group">
            <label class="form-label">Company Logo</label>

            <?php
                $logo = $company['logo'] ?? '';
                $logoFile = $logo !== '' ? base_path('public/uploads/tenants/' . basename($logo)) : null;
            ?>

            <?php if ($logoFile && is_file($logoFile)): ?>
                <div class="u-mb-s3">
                    <img src="/uploads/tenants/<?= htmlspecialchars(basename($logo)) ?>"
                         alt="Company logo"
                         class="u-maxh-64 u-maxw-200 u-ofit-contain">
                </div>
            <?php endif; ?>

            <input type="file"
                   name="logo"
                   class="form-control"
                   accept="image/jpeg,image/png,image/webp">
            <small class="form-text">JPG, PNG or WebP up to 2 MB. Shown in the sidebar and on the login page.</small>
        </div>

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