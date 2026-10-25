<?php

namespace App\Modules\Notifications\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Notifications\Services\NotificationService;
use Throwable;

/**
 * The user's notification inbox, channel preferences and push devices.
 *
 * Every action is limited to the signed-in user's own rows; permissions come from
 * notifications.view / notifications.update.
 */
class NotificationController extends Controller
{
    private NotificationService $service;

    public function __construct()
    {
        $this->service = new NotificationService();
    }

    private function actor(): array
    {
        return [
            'tenant_id' => Tenant::require(),
            'user_id'   => (int) Auth::id(),
        ];
    }

    public function index(Request $request)
    {
        if (!can('notifications.view')) {
            Response::abort(403);
        }

        $actor   = $this->actor();
        $unreadOnly = $request->input('filter') === 'unread';

        $page = $this->service->page(
            $actor['tenant_id'],
            $actor['user_id'],
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 20),
            $unreadOnly
        );

        return $this->view('Notifications::index', [
            'title'        => 'Notifications',
            'notifications' => $page['data'],
            'data'          => ['meta' => $page['meta']],
            'unread'        => $this->service->unreadCount($actor['tenant_id'], $actor['user_id']),
            'filter'        => $unreadOnly ? 'unread' : 'all',
        ]);
    }

    /** Mark one notification read, then follow its link (that is what a click means). */
    public function read(Request $request, $id)
    {
        if (!can('notifications.view')) {
            Response::abort(403);
        }

        $actor = $this->actor();

        try {
            $link = $this->service->markRead($actor['tenant_id'], $actor['user_id'], (int) $id);
        } catch (Throwable $e) {
            Response::abort(404, $e->getMessage());
        }

        // "Mark read" without opening: stay on the feed.
        if ($request->input('stay')) {
            Response::redirect('/notifications' . ($request->input('filter') ? '?filter=' . rawurlencode((string) $request->input('filter')) : ''));
        }

        Response::redirect($link !== '' ? $link : '/notifications');
    }

    public function readAll(Request $request)
    {
        if (!can('notifications.view')) {
            Response::abort(403);
        }

        $actor = $this->actor();
        $this->service->markAllRead($actor['tenant_id'], $actor['user_id']);

        Response::redirect('/notifications?filter=unread&cleared=1');
    }

    public function preferences(Request $request)
    {
        if (!can('notifications.view')) {
            Response::abort(403);
        }

        $actor = $this->actor();

        return $this->view('Notifications::preferences', [
            'title'   => 'Notification Preferences',
            'grid'    => $this->service->preferences($actor['tenant_id'], $actor['user_id']),
            'devices' => $this->service->devices($actor['tenant_id'], $actor['user_id']),
        ]);
    }

    public function savePreferences(Request $request)
    {
        if (!can('notifications.update')) {
            Response::abort(403);
        }

        $actor    = $this->actor();
        $input    = $request->input('preferences');
        $input    = is_array($input) ? $input : [];
        $switched = $this->service->savePreferences($actor['tenant_id'], $actor['user_id'], $input);

        return $this->view('Notifications::preferences', [
            'title'   => 'Notification Preferences',
            'grid'    => $this->service->preferences($actor['tenant_id'], $actor['user_id']),
            'devices' => $this->service->devices($actor['tenant_id'], $actor['user_id']),
            'saved'   => $switched > 0 ? 'Your notification channels are updated.' : 'No changes were saved.',
        ]);
    }

    /** Push token enrolment for the mobile/web client. Returns JSON. */
    public function registerDevice(Request $request)
    {
        if (!can('notifications.update')) {
            Response::abort(403);
        }

        $actor = $this->actor();

        try {
            $created = $this->service->registerDevice(
                $actor['tenant_id'],
                $actor['user_id'],
                (string) $request->input('token'),
                (string) $request->input('platform', 'web')
            );
        } catch (Throwable $e) {
            Response::abort(422, $e->getMessage());
        }

        $this->json(['status' => $created ? 'registered' : 'refreshed'], 201);
    }

    public function deleteDevice(Request $request, $id)
    {
        if (!can('notifications.update')) {
            Response::abort(403);
        }

        $actor = $this->actor();
        $this->service->removeDevice($actor['tenant_id'], $actor['user_id'], (int) $id);

        Response::redirect('/notifications/preferences');
    }
}
