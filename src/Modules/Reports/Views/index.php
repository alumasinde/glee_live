<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-chart-column"></i> <?= htmlspecialchars($title) ?>
    </h1>
</div>

<div class="report-dashboard">

    <a href="/reports/gatepasses" class="report-card">
        <div class="card-icon">
            <i class="fa-solid fa-file-signature"></i>
        </div>
        <h3>Gatepass Reports</h3>
        <p>View and analyze all gatepass records</p>
    </a>

    <a href="/reports/visitors" class="report-card">
        <div class="card-icon">
            <i class="fa-solid fa-user"></i>
        </div>
        <h3>Visitor Reports</h3>
        <p>Visitor registration and activity reports</p>
    </a>

    <a href="/reports/visits" class="report-card">
        <div class="card-icon">
            <i class="fa-solid fa-right-to-bracket"></i>
        </div>
        <h3>Visit Reports</h3>
        <p>Check-in and check-out tracking</p>
    </a>

    <?php if (can('reports.view')): ?>
        <a href="/reports/occupancy" class="report-card">
            <div class="card-icon">
                <i class="fa-solid fa-warehouse"></i>
            </div>
            <h3>Occupancy</h3>
            <p>Who and what is on site right now, per gate</p>
        </a>
    <?php endif; ?>

    <?php if (can('audit.view')): ?>
        <a href="/reports/audit-logs" class="report-card">
            <div class="card-icon">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <h3>Audit Logs</h3>
            <p>System activity and user actions</p>
        </a>
    <?php endif; ?>

</div>
