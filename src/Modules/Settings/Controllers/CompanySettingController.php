<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Tenant\Services\TenantService;
use PDO;

class CompanySettingController extends Controller
{
    private PDO $db;
    private int $tenantId;

    public function __construct()
    {
        // FIX: Use Auth/Tenant helpers instead of reading $_SESSION directly
        if (! Auth::check()) {
            Response::redirect('/login');
        }

        $this->tenantId = Tenant::require();
        $this->db       = DB::connect();
    }

    /**
     * Show company settings page
     */
    public function index()
    {
        $stmt = $this->db->prepare("
            SELECT name, email, code, phone, country, logo
            FROM tenants
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $this->tenantId]);

        $company = $stmt->fetch(PDO::FETCH_ASSOC);

        return $this->view('Settings::company', [
            'title'   => 'Company Settings',
            'company' => $company ?: [],
        ]);
    }

    /**
     * Update company settings
     */
    public function update(Request $request)
    {
        $stmt = $this->db->prepare("
            UPDATE tenants
            SET name       = :name,
                email      = :email,
                code       = :code,
                phone      = :phone,
                country    = :country,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        $stmt->execute([
            ':name'    => trim($request->input('company_name') ?? ''),
            ':email'   => trim($request->input('email')        ?? ''),
            ':code'    => trim($request->input('code')         ?? ''),
            ':phone'   => trim($request->input('phone')        ?? ''),
            ':country' => trim($request->input('country')      ?? ''),
            ':id'      => $this->tenantId,
        ]);

        $flashType    = 'success';
        $flashMessage = 'Company settings updated.';

        $logo = $_FILES['logo'] ?? null;

        if ($logo && ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($logo['error'] === UPLOAD_ERR_OK && $logo['size'] > 2 * 1024 * 1024) {
                $flashType    = 'error';
                $flashMessage = 'Settings saved, but the logo was too large (max 2 MB).';
            } elseif ((new TenantService())->uploadAndSaveLogo($logo, $this->tenantId)) {
                $stmt = $this->db->prepare("SELECT logo FROM tenants WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $this->tenantId]);
                $_SESSION['user']['tenant_logo'] = $stmt->fetchColumn() ?: '';
            } else {
                $flashType    = 'error';
                $flashMessage = 'Settings saved, but the logo upload failed. Use a JPG, PNG or WebP image under 2 MB.';
            }
        }

        $_SESSION['flash'] = ['type' => $flashType, 'message' => $flashMessage];

        // FIX: Use Response::redirect() instead of raw header() + exit
        return $this->redirect('/settings/company');
    }
}