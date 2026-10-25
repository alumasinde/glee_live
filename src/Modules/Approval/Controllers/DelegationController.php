<?php

namespace App\Modules\Approval\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Approval\Services\DelegationService;
use Throwable;


/**
 * Approval delegation (out-of-office coverage) for the signed-in user.
 *
 * Users manage only their own delegations; the permission gate is
 * approval.delegate. Live pending approvals are not moved here — that stays an
 * admin action (ApprovalService::reassign) so a colleague going away cannot
 * silently rewrite work that is already in someone's queue.
 */
class DelegationController extends Controller
{
    private DelegationService $delegations;

    public function __construct()
    {
        $this->delegations = new DelegationService();
    }

    private function userId(): int
    {
        return (int) Auth::id();
    }

    public function index(Request $request)
    {
        if (!can('approval.delegate')) {
            Response::abort(403);
        }

        $tenantId = Tenant::require();
        $userId   = $this->userId();

        return $this->view('Approval::delegation', [
            'title'      => 'Approval Delegation',
            'outgoing'   => $this->delegations->outgoing($tenantId, $userId),
            'incoming'   => $this->delegations->incoming($tenantId, $userId),
            'candidates' => $this->delegations->candidates($tenantId, $userId),
            'roles'      => $this->delegations->approvableRoles($tenantId, $userId),
            'error'      => $request->input('error'),
        ]);
    }

    public function store(Request $request)
    {
        if (!can('approval.delegate')) {
            Response::abort(403);
        }

        $tenantId = Tenant::require();
        $userId   = $this->userId();

        $delegateId = (int) $request->input('delegate_user_id');
        $roleId     = $request->input('role_id');
        $roleId     = $roleId === null || $roleId === '' ? null : (int) $roleId;

        $startsAt = $this->toMysql((string) $request->input('starts_at', ''), 'now');
        $endsAt   = $this->toMysql((string) $request->input('ends_at', ''), '+7 days');

        if ($delegateId <= 0) {
            Response::redirect('/approvals/delegation?error=' . rawurlencode('Choose a colleague to cover your approvals.'));
        }

        try {
            $this->delegations->create(
                $tenantId,
                $userId,
                $delegateId,
                $roleId,
                $startsAt,
                $endsAt,
                trim((string) $request->input('note', '')) ?: null
            );
        } catch (Throwable $e) {
            Response::redirect('/approvals/delegation?error=' . rawurlencode($e->getMessage()));
        }

        Response::redirect('/approvals/delegation');
    }

    public function destroy(Request $request, $id)
    {
        if (!can('approval.delegate')) {
            Response::abort(403);
        }

        try {
            $this->delegations->delete(Tenant::require(), $this->userId(), (int) $id);
        } catch (Throwable $e) {
            Response::redirect('/approvals/delegation?error=' . rawurlencode($e->getMessage()));
        }

        Response::redirect('/approvals/delegation');
    }

    /** datetime-local input (or empty) => MySQL datetime. */
    private function toMysql(string $value, string $fallback): string
    {
        $timestamp = $value === '' ? strtotime($fallback) : strtotime(str_replace('T', ' ', $value));

        return $timestamp === false
            ? date('Y-m-d H:i:s', strtotime($fallback))
            : date('Y-m-d H:i:s', $timestamp);
    }
}
