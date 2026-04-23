<aside class="sidebar" id="sidebar">

    <div class="brand">
        <i class="fa-solid fa-id-badge"></i>
        <span>
            <?= htmlspecialchars($_SESSION['user']['tenant_code'] ?? '') ?>
        </span>    
    </div>

    <ul class="menu">

        <li>
            <a href="/dashboard" class="<?= $current === '/dashboard' ? 'active' : '' ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <li>
            <a href="/gatepasses" class="<?= $current === '/gatepasses' ? 'active' : '' ?>">
                <i class="fa-solid fa-file-signature"></i>
                <span>Gatepasses</span>
            </a>
        </li>

        <li>
            <a href="/visitors" class="<?= $current === '/visitors' ? 'active' : '' ?>">
                <i class="fa-solid fa-user"></i>
                <span>Visitors</span>
            </a>
        </li>

        <li>
            <a href="/visits" class="<?= $current === '/visits' ? 'active' : '' ?>">
                <i class="fa-solid fa-right-to-bracket"></i>
                <span>Visits</span>
            </a>
        </li>

        <li>
            <a href="/approvals" class="<?= $current === '/approvals' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-check"></i>
                <span>Approvals</span>
            </a>
        </li>

        <li>
            <a href="/reports" class="<?= str_starts_with($current, '/reports') ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-column"></i>
                <span>Reports</span>
            </a>
        </li>

        <li>
            <a href="/roles" class="<?= $current === '/roles' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Roles</span>
            </a>
        </li>

        <li>
            <a href="/settings" class="<?= str_starts_with($current, '/settings') ? 'active' : '' ?>">
                <i class="fa-solid fa-gear"></i>
                <span>Settings</span>
            </a>
        </li>

    </ul>

</aside>