<?php

namespace App\Modules\Platform\Controllers;

use App\Core\PlatformPolicy;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Platform\Services\PlatformAuditService;
use App\Modules\Platform\Services\PlatformSettingsService;

class PlatformSettingsController
{
    public function index(Request $request): mixed
    {
        $settings = new PlatformSettingsService();

        View::render('Platform::features', [
            'title'      => 'Features & permissions',
            'features'   => PlatformPolicy::features(),
            'matrix'     => $settings->permissionMatrix(),
            'restricted' => PlatformPolicy::restrictedPermissions(),
        ], 'platform');

        return null;
    }

    public function update(Request $request): never
    {
        $platformUser = $_SESSION['platform_user'] ?? [];
        $actorId = !empty($platformUser['id']) ? (int) $platformUser['id'] : null;

        $features = $request->input('features', []);
        $features = is_array($features) ? $features : [];

        $restricted = $request->input('restricted_permissions', []);
        $restricted = is_array($restricted) ? $restricted : [];

        $settings = new PlatformSettingsService();
        $settings->saveFeatures($features, $actorId);
        $settings->saveRestrictedPermissions($restricted, $actorId);

        PlatformAuditService::log('platform.settings_updated', null, null, [
            'features'             => array_keys(array_filter($features)),
            'restricted_permissions' => $restricted,
        ]);

        $_SESSION['flash'] = ['message' => 'Platform policy saved.', 'type' => 'success'];
        Response::redirect('/platform/features');
    }
}
