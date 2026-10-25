<nav class="navbar">

    <div class="nav-left">

    <button id="sidebarToggle" class="toggle-btn">
        <i class="fa-solid fa-bars"></i>
    </button>

    <span class="page-title"><?= $title ?? '' ?></span>

</div>
<div class="nav-center">
    <?= htmlspecialchars($_SESSION ['user']['tenant_name'] ?? 'PMS') ?>
    </a>
</div>

<div class="nav-right">
   <?php
   // Unread badge count. A notification lookup must never break the chrome, so
   // it is guarded by the permission and by its own failure path.
   $unread = 0;

   if (function_exists('can') && can('notifications.view')) {
       try {
           $unread = (new \App\Modules\Notifications\Services\NotificationService())
               ->unreadCount(
                   (int) ($_SESSION['user']['tenant_id'] ?? 0),
                   (int) ($_SESSION['user']['id'] ?? 0)
               );
       } catch (\Throwable $e) {
           error_log('[navbar] unread count unavailable: ' . $e->getMessage());
       }
   }
   ?>
   <?php if (function_exists('can') && can('notifications.view')): ?>
       <a href="/notifications" class="notif-bell" aria-label="Notifications<?= $unread ? ' (' . $unread . ' unread)' : '' ?>"
          title="Notifications" style="position:relative; margin-right:var(--space-3);">
           <i class="fa-solid fa-bell"></i>
           <?php if ($unread > 0): ?>
               <span class="badge badge-danger"
                     style="position:absolute;top:-6px;right:-8px;font-size:0.6rem;">
                   <?= $unread > 99 ? '99+' : (int) $unread ?>
               </span>
           <?php endif; ?>
       </a>
   <?php endif; ?>

   <a href="/settings/users/profile" class="user-name">
    <i class="fa-solid fa-user"></i>
    <?= htmlspecialchars($_SESSION['user']['first_name'] ?? '') ?>
</a>

    <form method="POST" action="/logout">
        <input type="hidden" name="_token" value="<?= \App\Middleware\CSRFMiddleware::token() ?>">
        <button type="submit" class="logout-btn" style="background:none; border:none; padding:0; cursor:pointer;">
            <i class="fa-solid fa-right-from-bracket"></i>
        </button>
    </form>
</div>

</nav>
