<footer class="footer">
    <small>
        &copy; <?= date('Y') ?> 
        <?= htmlspecialchars($_SESSION ['user']['tenant_name'] ?? 'GPMS') ?>
        . All rights reserved.
    </small>
</footer>
