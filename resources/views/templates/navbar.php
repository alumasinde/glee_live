<nav class="navbar">

    <div class="nav-left">

    <button id="sidebarToggle" class="toggle-btn">
        <i class="fa-solid fa-bars"></i>
    </button>

    <span class="page-title"><?= $title ?? '' ?></span>

</div>
<div class="nav-center">
    <?= htmlspecialchars($_SESSION ['user']['tenant_name'] ?? 'GPMS') ?>
    </a>
</div>

<div class="nav-right">
   <a href="/settings/users/profile" class="user-name">
    <i class="fa-solid fa-user"></i>
    <?= htmlspecialchars($_SESSION['user']['first_name'] ?? '') ?>
</a>

    <form method="POST" action="/logout">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit" class="logout-btn" style="background:none; border:none; padding:0; cursor:pointer;">
            <i class="fa-solid fa-right-from-bracket"></i>
        </button>
    </form>
</div>

</nav>
