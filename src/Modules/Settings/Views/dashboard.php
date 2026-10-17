<h1 class="page-heading">Settings</h1>

<div class="report-dashboard">

    <?php if (can('settings.view')): ?>
        <a href="/settings/gatepass-numbering" class="report-card">
            <i class="fa-solid fa-hashtag"></i>
            <h3>Gatepass Numbering</h3>
            <p>Configure numbering format, prefix, reset rules.</p>
        </a>
    <?php endif; ?>

    <?php if (can('users.view')): ?>
        <a href="/settings/users" class="report-card">
            <i class="fa-solid fa-users"></i>
            <h3>User Management</h3>
            <p>Manage system users and access.</p>
        </a>
    <?php endif; ?>

    <?php if (can('departments.view')): ?>
        <a href="/settings/departments" class="report-card">
            <i class="fa-solid fa-building"></i>
            <h3>Departments</h3>
            <p>Manage organizational departments.</p>
        </a>
    <?php endif; ?>

    <?php if (can('settings.view')): ?>
        <a href="/settings/gatepass-types" class="report-card">
            <i class="fa-solid fa-lock"></i>
            <h3>Gatepass Type Config</h3>
            <p>Configure Gatepass Types.</p>
        </a>
    <?php endif; ?>

    <?php if (can('settings.view')): ?>
        <a href="/settings/company" class="report-card">
            <i class="fa-solid fa-building"></i>
            <h3>Company Info</h3>
            <p>Update organization details and branding.</p>
        </a>
    <?php endif; ?>

    <?php if (can('settings.view')): ?>
        <a href="/settings/email" class="report-card">
            <i class="fa-solid fa-envelope"></i>
            <h3>Email (SMTP)</h3>
            <p>Configure outgoing mail for visitor passes and notifications.</p>
        </a>
    <?php endif; ?>

    <?php if (can('settings.view')): ?>
        <a href="/settings/gates" class="report-card">
            <i class="fa-solid fa-door-open"></i>
            <h3>Gates</h3>
            <p>Manage physical gates, device keys and scan context.</p>
        </a>
    <?php endif; ?>

    <?php if (can('workflows.view')): ?>
        <a href="/settings/workflows" class="report-card">
            <i class="fa-solid fa-process"></i>
            <h3>Workflow Configuration</h3>
            <p>Update Company Workflow.</p>
        </a>
    <?php endif; ?>

</div>
