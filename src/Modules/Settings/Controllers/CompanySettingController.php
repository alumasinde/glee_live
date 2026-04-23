<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
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
            SELECT name, email, code, phone, country
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

        // FIX: Use Response::redirect() instead of raw header() + exit
        return $this->redirect('/settings/company');
    }
}