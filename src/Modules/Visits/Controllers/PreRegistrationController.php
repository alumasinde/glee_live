<?php

namespace App\Modules\Visits\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Visits\Services\PreRegistrationService;

class PreRegistrationController extends Controller
{
    private PreRegistrationService $service;

    public function __construct()
    {
        $this->service = new PreRegistrationService();
    }

    public function form(Request $request, mixed $code): mixed
    {
        $tenant = $this->service->findTenantByCode((string) $code);

        if (!$tenant) {
            Response::abort(404);
        }

        return View::render('Visits::pre_register', [
            'title'    => 'Pre-Register Your Visit',
            'tenant'   => $tenant,
            'options'  => $this->service->formOptions((int) $tenant['id']),
            'done'     => isset($request->all()['done']),
            'errors'   => [],
            'old'      => [],
        ], 'guest');
    }

    public function submit(Request $request, mixed $code): mixed
    {
        $tenant = $this->service->findTenantByCode((string) $code);

        if (!$tenant) {
            Response::abort(404);
        }

        $data = $request->all();

        // Honeypot: bots fill it, humans never see it. Pretend success.
        if (trim((string) ($data['website'] ?? '')) !== '') {
            return $this->successRedirect((string) $code);
        }

        // Per-session throttle: one submission every 15 seconds.
        $last = (int) ($_SESSION['_prereg_at'] ?? 0);
        if ($last > 0 && (time() - $last) < 15) {
            return $this->renderForm($tenant, $code, ['Please wait a moment before submitting again.'], $data);
        }

        $errors = $this->validate($tenant, $data);

        if ($errors) {
            return $this->renderForm($tenant, $code, $errors, $data);
        }

        $_SESSION['_prereg_at'] = time();

        $result = $this->service->register((int) $tenant['id'], $data);

        // Created, duplicate or blacklist-rejected: the public response is
        // identical so the form can't be used to probe who is banned.
        return $this->successRedirect((string) $code);
    }

    public function pass(Request $request, mixed $token): mixed
    {
        $token = strtolower(trim((string) $token));

        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            Response::abort(404);
        }

        $visit = $this->service->findByToken($token);

        if (!$visit) {
            Response::abort(404);
        }

        return View::render('Visits::pass', [
            'title' => 'Your Visit Pass',
            'visit' => $visit,
            'qr'    => \App\Core\QrCode::svgDataUri((string) $visit['scan_token']),
        ], 'guest');
    }

    /* ------------------------------------------------------------------ */

    private function successRedirect(string $code): mixed
    {
        return $this->redirect('/pre-register/' . rawurlencode($code) . '?done=1');
    }

    private function renderForm(array $tenant, string $code, array $errors, array $old): mixed
    {
        return View::render('Visits::pre_register', [
            'title'   => 'Pre-Register Your Visit',
            'tenant'  => $tenant,
            'options' => $this->service->formOptions((int) $tenant['id']),
            'done'    => false,
            'errors'  => $errors,
            'old'     => $old,
        ], 'guest');
    }

    private function validate(array $tenant, array $data): array
    {
        $errors = [];

        if (trim((string) ($data['first_name'] ?? '')) === '' || trim((string) ($data['last_name'] ?? '')) === '') {
            $errors[] = 'Your full name is required.';
        }

        if (!filter_var((string) ($data['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required (your pass link is sent there).';
        }

        if (trim((string) ($data['purpose'] ?? '')) === '' || strlen((string) $data['purpose']) > 500) {
            $errors[] = 'Purpose of visit is required (max 500 characters).';
        }

        $expectedIn = strtotime((string) ($data['expected_in'] ?? ''));
        if ($expectedIn === false) {
            $errors[] = 'A valid arrival date and time is required.';
        } elseif ($expectedIn < time() - 86400) {
            $errors[] = 'Arrival date cannot be in the past.';
        }

        $deptIds = array_column($this->service->formOptions((int) $tenant['id'])['departments'], 'id');
        if (!in_array((int) ($data['department_id'] ?? 0), array_map('intval', $deptIds), true)) {
            $errors[] = 'Please select the department you are visiting.';
        }

        if (!empty($data['host_user_id'])) {
            $hostIds = array_map('intval', array_column($this->service->formOptions((int) $tenant['id'])['hosts'], 'id'));
            if (!in_array((int) $data['host_user_id'], $hostIds, true)) {
                $errors[] = 'Invalid host selected.';
            }
        }

        return $errors;
    }
}
