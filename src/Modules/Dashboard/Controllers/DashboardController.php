<?php

namespace App\Modules\Dashboard\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Core\Audit;
use App\Modules\Dashboard\Services\DashboardService;

class DashboardController extends Controller
{
    private DashboardService $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    public function index()
    {
        $user = $_SESSION['user'];

        $stats = $this->service->getStats($user);

          $user = $_SESSION['user'];

          Audit::log(
        'dashboard.viewed',
        'dashboard',
        (int) $user['id'],
        [
            'email' => $user['email']
        ]
    );

          return View::render(
        'Dashboard::index',
        [
            'title' => 'Dashboard',
            'user'   => $user,
            'stats'  => $stats,
            'charts' => $this->service->getChartData($user['tenant_id'])
        ],
        'app'
    );

   
    }

    public function charts()
{
    $user = $_SESSION['user'];
    $days = (int)($_GET['days'] ?? 30);

    $data = $this->service->getChartData($user['tenant_id'], $days);

     
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

}
