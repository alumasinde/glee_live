<?php

namespace App\Modules\Approval\Controllers;

use App\Core\DB;
use App\Core\View;
use App\Core\Request;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Permission;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Approval\Policies\ApprovalPolicy;

class ApprovalController extends Controller
{
    private ApprovalService $service;
    private ApprovalPolicy $policy;

    public function __construct()
    {
        $this->service = new ApprovalService();

        $permission = new Permission(DB::connect());
        $this->policy = new ApprovalPolicy($permission);
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        return $_SESSION['user'];
    }

    /* =========================================================
     * INDEX (Pending Approvals)
     * ========================================================= */

    public function index()
    {
        $user = $this->user();

        if (!$this->policy->viewAny()) {
        Response::abort(403);        
        }

        $approvals = $this->service->getPendingForUser(
            $user['tenant_id'],
            $user['id']
        );

        return View::render('Approval::index', [
            'title' => 'My Approvals',
            'approvals' => $approvals
        ], 'app');
    }

    /* =========================================================
     * APPROVE
     * ========================================================= */

    public function approve(Request $request, $id)
    {
        $user = $this->user();

        if (!$this->policy->approve()) {
        Response::abort(403);        
        }

        $approvalId = (int) $id;

        if ($approvalId <= 0) {
        Response::abort(400);        
        }

        try {

            $this->service->approve(
                $approvalId,
                $user['id']
            );

            header('Location: /approvals');
            exit;

        } catch (\Throwable $e) {

            return View::render('Approval::index', [
                'title' => 'My Approvals',
                'error' => $e->getMessage(),
                'approvals' => $this->service->getPendingForUser(
                    $user['tenant_id'],
                    $user['id']
                )
            ], 'app');
        }
    }

    /* =========================================================
     * REJECT
     * ========================================================= */

    public function reject(Request $request, $id)
    {
        $user = $this->user();

        if (!$this->policy->reject()) {
        Response::abort(403);        
        }

        $approvalId = (int) $id;

        if ($approvalId <= 0) {
        Response::abort(400);        
        }

        $reason = trim($request->input('reason', ''));

        if ($reason === '') {
        Response::abort(422, 'Rejection reason is required.');
        }

        try {

            $this->service->reject(
                $approvalId,
                $user['id'],
                $reason
            );

            header('Location: /approvals');
            exit;

        } catch (\Throwable $e) {

            return View::render('Approval::index', [
                'title' => 'My Approvals',
                'error' => $e->getMessage(),
                'approvals' => $this->service->getPendingForUser(
                    $user['tenant_id'],
                    $user['id']
                )
            ], 'app');
        }
    }

    /* =========================================================
     * SHOW (Optional - single approval view)
     * ========================================================= */

    public function show(Request $request, $id)
{
    $user = $this->user();

    if (!$this->policy->viewAny()) {
        Response::abort(403);
    }

    $approvalId = (int) $id;

    if ($approvalId <= 0) {
        Response::abort(400);
    }

    $approval = $this->service->findApproval(
    $user['tenant_id'],
    $approvalId,   // pass ga.id
    $user['id']
);

    /* if (!$approval) {
        Response::abort(404);
    } */

    // 🔐 Object-level authorization
    if (!$this->policy->view($user, $approval)) {
        Response::abort(403);
    }

    return View::render('Approval::show', [
        'title' => 'View Approval',
        'approval' => $approval
    ], 'app');
}

}