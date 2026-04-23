<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Tenant\Repositories\TenantRepository;
use finfo;

class TenantService
{
    private TenantRepository $tenantRepo;
    private string $uploadPath;

    public function __construct()
    {
        $this->tenantRepo = new TenantRepository();
        $this->uploadPath = __DIR__ . '/../../../public/uploads/tenants/';
    }

    public function uploadAndSaveLogo(array $file, int $tenantId): bool
    {
        // Validate upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        // Limit size (2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return false;
        }

        // Validate MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);

        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowed[$mime])) {
            return false;
        }

        // Generate filename
        $filename = sprintf(
            'tenant_%d_%s.%s',
            $tenantId,
            bin2hex(random_bytes(8)),
            $allowed[$mime]
        );

        $destination = $this->uploadPath . $filename;

        // Ensure directory exists
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 755, true);
        }

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return false;
        }

        // Delete old logo (if exists)
        $this->deleteOldLogo($tenantId);

        // Save to DB
        return $this->tenantRepo->updateLogo($tenantId, $filename);
    }

    private function deleteOldLogo(int $tenantId): void
    {
        $tenant = $this->tenantRepo->findById($tenantId);

        if (!$tenant || empty($tenant['logo'])) {
            return;
        }

        $oldFile = $this->uploadPath . basename($tenant['logo']);

        if (is_file($oldFile)) {
            unlink($oldFile);
        }
    }
}