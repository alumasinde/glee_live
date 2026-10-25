<?php
/**
 * In-app notification feed.
 *
 * @var list<array> $notifications
 * @var array       $data    ['meta' => pagination meta]
 * @var int         $unread
 * @var string      $filter  'all' | 'unread'
 */

use App\Middleware\CSRFMiddleware;

$meta = $data['meta'] ?? [];

$link = static function (string $filter): string {
    return $filter === 'unread' ? '/notifications?filter=unread' : '/notifications';
};
?>
<div class="page-header">
    <h1 class="page-heading">
        <i class="fa-solid fa-bell"></i> <?= htmlspecialchars($title ?? 'Notifications') ?>
        <?php if ($unread > 0): ?>
            <span class="badge badge-danger"><?= (int) $unread ?> new</span>
        <?php endif; ?>
    </h1>
    <div class="page-actions">
        <a class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline' ?>" href="<?= $link('all') ?>">All</a>
        <a class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline' ?>" href="<?= $link('unread') ?>">Unread</a>
        <a class="btn btn-sm btn-secondary" href="/notifications/preferences">
            <i class="fa-solid fa-sliders"></i> Channels
        </a>
        <?php if ($unread > 0): ?>
            <form method="POST" action="/notifications/read-all" class="inline-form">
                <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">
                <button type="submit" class="btn btn-sm btn-outline">Mark all read</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_GET['cleared'])): ?>
    <div class="gp-alert gp-alert--success">Unread count cleared.</div>
<?php endif; ?>

<div class="table-card">
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <i class="fa-solid fa-bell-slash"></i>
            <div class="empty-state-title">Nothing here</div>
            <div class="empty-state-text">
                <?= $filter === 'unread' ? 'You have read everything.' : 'No notifications have been sent to you yet.' ?>
            </div>
        </div>
    <?php else: ?>
        <ul class="notification-list" style="list-style:none; margin:0; padding:0;">
            <?php foreach ($notifications as $note): ?>
                <li class="table-card" style="box-shadow:none; border-bottom:1px solid rgba(0,0,0,.06); margin:0;">
                    <div class="card-body" style="padding:var(--space-3) 0;">
                        <div class="d-flex justify-content-between" style="gap:var(--space-3);">
                            <div>
                                <?php if (empty($note['read_at'])): ?>
                                    <span class="badge badge-danger">new</span>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($note['title']) ?></strong>
                                <p class="form-text" style="margin:var(--space-1) 0 0;">
                                    <?= nl2br(htmlspecialchars($note['body'] ?? '')) ?>
                                </p>
                            </div>
                            <div class="cell-date" style="white-space:nowrap;">
                                <?= format_date($note['created_at'] ?? null) ?>
                            </div>
                        </div>
                        <div class="table-actions" style="margin-top:var(--space-2);">
                            <?php if (!empty($note['link'])): ?>
                                <a class="btn btn-sm btn-primary" href="/notifications/<?= (int) $note['id'] ?>/read">
                                    Open
                                </a>
                            <?php endif; ?>
                            <?php if (empty($note['read_at'])): ?>
                                <form method="POST" action="/notifications/<?= (int) $note['id'] ?>/read" class="inline-form">
                                    <input type="hidden" name="_token" value="<?= CSRFMiddleware::token() ?>">
                                    <input type="hidden" name="stay" value="1">
                                    <button type="submit" class="btn btn-sm btn-outline">Mark read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php include base_path('resources/views/partials/pagination.php'); ?>
    <?php endif; ?>
</div>
