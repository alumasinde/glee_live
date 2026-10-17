<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\TenantSettingService;

class EmailSettingController extends Controller
{
    public function __construct()
    {
        if (! Auth::check()) {
            Response::redirect('/login');
        }
    }

    public function index(Request $request)
    {
        $settingsService = new TenantSettingService();

        $config = array_merge(Mailer::defaults(), $settingsService->get('mail'));

        // Never round-trip the SMTP password to the browser.
        $config['has_password'] = $config['password'] !== '';
        $config['password']     = '';

        $notice = $this->noticeFromQuery($request);

        return $this->view('Settings::email', [
            'title'  => 'Email (SMTP) Settings',
            'config' => $config,
            'notice' => $notice,
        ]);
    }

    public function update(Request $request)
    {
        $settingsService = new TenantSettingService();

        $existing = array_merge(Mailer::defaults(), $settingsService->get('mail'));

        $encryption = (string) $request->input('encryption', 'tls');
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
            $encryption = 'tls';
        }

        $newPassword = (string) ($request->input('password') ?? '');

        $config = [
            'enabled'      => (bool) $request->input('enabled'),
            'host'         => trim((string) $request->input('host', '')),
            'port'         => max(1, min(65535, (int) ($request->input('port') ?: 587))),
            'encryption'   => $encryption,
            'username'     => trim((string) $request->input('username', '')),
            'password'     => $newPassword !== '' ? $newPassword : $existing['password'],
            'from_address' => trim((string) $request->input('from_address', '')),
            'from_name'    => trim((string) $request->input('from_name', '')),
            'insecure_tls' => (bool) $request->input('insecure_tls'),
        ];

        $settingsService->set('mail', $config);

        return $this->redirect('/settings/email?saved=1');
    }

    public function test(Request $request)
    {
        $to       = trim((string) $request->input('test_to', ''));
        $tenantId = (int) Auth::tenantId();
        $user     = Auth::user() ?? [];

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->redirect('/settings/email?test=invalid');
        }

        $status = Mailer::send(
            $tenantId,
            $to,
            'SMTP test from ' . ($user['tenant_name'] ?? 'TuPass'),
            '<p>This is a test message from the gate pass system. '
                . 'If you received it, your SMTP settings work.</p>'
        );

        return $this->redirect('/settings/email?test=' . $status);
    }

    private function noticeFromQuery(Request $request): ?string
    {
        $params = $request->all();

        if (!empty($params['saved'])) {
            return 'Email settings saved.';
        }

        return match ($params['test'] ?? '') {
            'sent'    => 'Test email sent successfully.',
            'failed'  => 'Test email failed. Check host/credentials (see error in mail log).',
            'skipped' => 'Test email skipped: mail is disabled or host/from address are not configured.',
            'invalid' => 'Enter a valid recipient address for the test.',
            default   => null,
        };
    }
}
