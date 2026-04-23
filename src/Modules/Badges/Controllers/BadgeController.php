<?php

declare(strict_types=1);

namespace App\Modules\Badges\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Modules\Badges\Services\BadgeService;

final class BadgeController
{
    private BadgeService $service;

    public function __construct()
    {
        $this->service = new BadgeService();
    }

    /*
    |--------------------------------------------------------------------------
    | AUTH USER
    |--------------------------------------------------------------------------
    */
    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }

        return $_SESSION['user'];
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUE BADGE
    |--------------------------------------------------------------------------
    */
public function issue(Request $request, int $visitId): void
    {
        $user = $this->user();

        try {

            $badgeCode = $this->service->issue(
                $user['tenant_id'],
                $visitId
            );

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => "Badge issued successfully. Code: {$badgeCode}"
            ];

        } catch (\Throwable $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visits');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN BADGE
    |--------------------------------------------------------------------------
    */
public function return(Request $request, int $visitId): void
    {
        $user = $this->user();

        try {

            $this->service->returnBadge(
                $user['tenant_id'],
                $visitId
            );

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Badge returned successfully.'
            ];

        } catch (\Throwable $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visits');
        exit;
    }
}