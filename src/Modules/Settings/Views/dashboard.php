<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-gear"></i> Settings
    </h1>
</div>

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
            <i class="fa-solid fa-id-badge"></i>
            <h3>Gatepass Types</h3>
            <p>Configure which actions each type permits.</p>
        </a>
    <?php endif; ?>

    <?php if (can('settings.view')): ?>
        <a href="/settings/company" class="report-card">
            <i class="fa-solid fa-building-columns"></i>
            <h3>Company Info</h3>
            <p>Update organization details and branding.</p>
        </a>
    <?php endif; ?>

    <?php if (can('email.view')): ?>
        <a href="/settings/email" class="report-card">
            <i class="fa-solid fa-envelope"></i>
            <h3>Email (SMTP)</h3>
            <p>Configure outgoing mail for visitor passes and notifications.</p>
        </a>
    <?php endif; ?>

    <?php if (can('gates.view')): ?>
        <a href="/settings/gates" class="report-card">
            <i class="fa-solid fa-door-open"></i>
            <h3>Gates</h3>
            <p>Manage physical gates, device keys and scan context.</p>
        </a>
    <?php endif; ?>

    <?php if (can('workflows.view')): ?>
        <a href="/settings/workflows" class="report-card">
            <i class="fa-solid fa-diagram-project"></i>
            <h3>Workflows</h3>
            <p>Define approval steps and assign them to gatepass types.</p>
        </a>
    <?php endif; ?>

</div>
