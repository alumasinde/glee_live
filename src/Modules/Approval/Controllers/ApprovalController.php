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

    public function index(Request $request)
    {
        return $this->renderList($request);
    }

    /** The pending queue, paged and aged. Also the fallback render after a failed decision. */
    private function renderList(Request $request, ?string $error = null)
    {
        $user = $this->user();

        if (!$this->policy->viewAny()) {
            Response::abort(403);
        }

        $sort = $request->input('sort') === 'recent' ? 'recent' : 'age';

        $page = $this->service->paginatePending(
            (int) $user['tenant_id'],
            (int) $user['id'],
            $sort,
            (int) $request->input('page', 1),
            (int) $request->input('per_page', 20)
        );

        return View::render('Approval::index', [
            'title'         => 'My Approvals',
            'error'         => $error,
            'approvals'     => $page['data'],
            'data'          => ['meta' => $page['meta']],
            'sort'          => $sort,
            'reminderHours' => $this->service->reminderHours(),
        ], 'app');
    }

    /* =========================================================
     * CONFIRM FORMS (GET)
     * ========================================================= */

    public function approveForm(Request $request, $id)
    {
        return $this->renderConfirmation('Approval::approve', $id);
    }

    public function rejectForm(Request $request, $id)
    {
        return $this->renderConfirmation('Approval::reject', $id);
    }

    private function renderConfirmation(string $view, int|string $id)
    {
        $user = $this->user();

        $allowed = $view === 'Approval::approve' ? $this->policy->approve() : $this->policy->reject();

        if (!$allowed) {
            Response::abort(403);
        }

        $approval = $this->service->findUserApproval(
            $user['tenant_id'],
            (int) $id,
            $user['id']
        );

        if (!$approval) {
            Response::abort(404, 'Approval not found or already processed.');
        }

        return View::render($view, [
            'title' => $view === 'Approval::approve' ? 'Approve Gatepass' : 'Reject Gatepass',
            'approval' => $approval
        ], 'app');
    }

    /* =========================================================
     * APPROVE (POST)
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
                $user['id'],
                trim((string) $request->input('comment', '')) ?: null
            );

            header('Location: /approvals');
            exit;

        } catch (\Throwable $e) {
            return $this->renderList($request, $e->getMessage());
        }
    }

    /* =========================================================
     * REJECT (POST)
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

        $reason = trim((string) ($request->input('reason', '') ?: $request->input('comment', '')));

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
            return $this->renderList($request, $e->getMessage());
        }
    }

    /* =========================================================
     * REASSIGN (approval.reassign)
     * ========================================================= */

    public function reassignForm(Request $request, $id)
    {
        $user = $this->user();

        if (!$this->policy->reassign()) {
            Response::abort(403);
        }

        $approval = $this->service->findApprovalForReassign($user['tenant_id'], (int) $id);

        if (!$approval) {
            Response::abort(404, 'Approval not found.');
        }

        return View::render('Approval::reassign', [
            'title'      => 'Reassign Approval',
            'approval'   => $approval,
            'stepPeers'  => $this->service->stepApprovals(
                $user['tenant_id'],
                (int) $approval['workflow_step_id'],
                (int) $approval['workflow_instance_id']
            ),
            'candidates' => $this->service->reassignCandidates(
                $user['tenant_id'],
                (int) $approval['requester_id'],
                (int) $approval['approver_user_id']
            ),
        ], 'app');
    }

    public function reassign(Request $request, $id)
    {
        $user = $this->user();

        if (!$this->policy->reassign()) {
            Response::abort(403);
        }

        $approvalId = (int) $id;
        $newUserId  = (int) $request->input('approver_user_id');

        if ($approvalId <= 0 || $newUserId <= 0) {
            Response::abort(422, 'A reassignment target is required.');
        }

        try {
            $this->service->reassign($approvalId, $newUserId, (int) $user['tenant_id']);

            header('Location: /approvals/' . $approvalId);
            exit;

        } catch (\Throwable $e) {
            Response::abort(422, $e->getMessage());
        }
    }

    /* =========================================================
     * SHOW
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
            $approvalId,
            $user['id']
        );

        // Admins with reassign rights can open approvals assigned to others.
        if (!$approval && $this->policy->reassign()) {
            $approval = $this->service->findApprovalForReassign($user['tenant_id'], $approvalId);
        }

        if (!$approval) {
            Response::abort(404, 'Approval not found.');
        }

        if (!$this->policy->view($user, $approval)) {
            Response::abort(403);
        }

        return View::render('Approval::show', [
            'title' => 'View Approval',
            'approval' => $approval
        ], 'app');
    }
}
