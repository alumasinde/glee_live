<?php

namespace App\Modules\Tenant\Controllers;

use App\Modules\Tenant\Services\TenantService;

class TenantController
{
    private TenantService $tenantService;

    public function __construct()
    {
        $this->tenantService = new TenantService();
    }

    public function uploadLogo(): void
    {
        // Ensure session is already started in bootstrap
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        // Enforce POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }

        // Auth check
        if (empty($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        // Validate tenant
        $tenantId = (int) ($_SESSION['user']['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            http_response_code(403);
            exit('Invalid tenant');
        }

        // CSRF protection
        if (
            empty($_POST['csrf_token']) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            http_response_code(403);
            exit('Invalid CSRF token');
        }

        // Validate file
        if (
            empty($_FILES['logo']) ||
            $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE ||
            !is_uploaded_file($_FILES['logo']['tmp_name'])
        ) {
            $_SESSION['flash']['error'] = 'No file uploaded';
            header('Location: /settings');
            exit;
        }

        // Call service (must return filename or null)
        $filename = $this->tenantService->uploadAndSaveLogo(
            $_FILES['logo'],
            $tenantId
        );

        if (!$filename) {
            $_SESSION['flash']['error'] = 'Logo upload failed';
        } else {
            $_SESSION['flash']['success'] = 'Logo updated successfully';

            // Immediate UI consistency
            $_SESSION['user']['tenant_logo'] = $filename;
        }
        header('Location: /settings');
        exit;
    }

    
}