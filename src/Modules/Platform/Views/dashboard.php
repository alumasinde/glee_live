<div class="platform-heading-row">
    <h1 class="page-heading">Tenants</h1>
    <a class="btn btn-primary" href="/platform/tenants/create">New tenant</a>
</div>

<p class="platform-meta">
    Module entitlements and platform policies apply per tenant. Tenant administrators
    can neither enable a module nor grant a platform-restricted permission.
</p>

<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>Tenant</th>
                <th>Code</th>
                <th>Status</th>
                <th>Users</th>
                <th>Gatepasses</th>
                <th>Modules disabled</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($tenants as $tenant): ?>
            <?php $disabled = (int) $tenant['disabled_count']; ?>
            <tr>
                <td>
                    <span class="cell-main"><?= htmlspecialchars((string) $tenant['name']) ?></span>
                    <?php if (!empty($tenant['email'])): ?>
                        <span class="cell-sub"><?= htmlspecialchars((string) $tenant['email']) ?></span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) $tenant['code']) ?></td>
                <td>
                    <span class="badge badge-<?= !empty($tenant['is_active']) ? 'approved' : 'rejected' ?>">
                        <?= !empty($tenant['is_active']) ? 'Active' : 'Suspended' ?>
                    </span>
                </td>
                <td><?= (int) $tenant['user_count'] ?></td>
                <td><?= (int) $tenant['gatepass_count'] ?></td>
                <td>
                    <?= $disabled === 0 ? 'None' : $disabled . ' of ' . (int) $moduleTotal ?>
                </td>
                <td class="text-end">
                    <a class="btn btn-primary" href="/platform/tenants/<?= (int) $tenant['id'] ?>">
                        Manage modules
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
