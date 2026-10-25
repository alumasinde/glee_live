<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Auth\Repositories\MfaRepository;
use App\Modules\Auth\Services\TwoFactorService;

/**
 * Self-service two-factor enrolment.
 *
 * The plaintext TOTP secret exists only in the session between setup() and
 * enable(); users.mfa_secret is written encrypted and recovery codes are
 * persisted as digests, so nothing here can ever reveal a secret after the
 * enrolment window closes.
 */
class TwoFactorController extends Controller
{
    private const SETUP_KEY = 'mfa_setup';

    private TwoFactorService $twoFactor;
    private MfaRepository $mfa;

    public function __construct()
    {
        $this->twoFactor = new TwoFactorService();
        $this->mfa = new MfaRepository();
    }

    public function index(Request $request)
    {
        $this->guard();

        $actor = $this->actor();
        $user  = $this->userRow($actor);

        if ($this->isEnabled($user)) {
            return $this->render('enabled', $actor, [
                'enabled_at' => $user['mfa_enabled_at'] ?? null,
                'recovery_remaining' => $this->twoFactor->remainingRecoveryCodes($actor['tenant_id'], $actor['user_id']),
            ]);
        }

        $secret = $this->beginSetup($actor, $user);

        return $this->render('setup', $actor, [
            'secret'      => $this->twoFactor->formatSecret($secret),
            'qr'          => $this->twoFactor->provisioningQr(
                $this->twoFactor->provisioningUri($user, $secret)
            ),
            'expires_at'  => $_SESSION[self::SETUP_KEY]['expires_at'] ?? time(),
        ]);
    }

    public function enable(Request $request)
    {
        $this->guard();

        $actor = $this->actor();
        $user  = $this->userRow($actor);

        if ($this->isEnabled($user)) {
            Response::abort(422, 'Two-factor authentication is already active on this account.');
        }

        $secret = $this->pendingSecret();

        if ($secret === null) {
            return $this->render('setup', $actor, [
                'error' => 'This set-up has expired. Start again to get a new code.',
            ]);
        }

        if (!$this->confirmPassword($request, $user)) {
            return $this->render('setup', $actor, [
                'error'  => 'Your password is incorrect.',
                'secret' => $this->twoFactor->formatSecret($secret),
                'qr'     => $this->twoFactor->provisioningQr(
                    $this->twoFactor->provisioningUri($user, $secret)
                ),
            ]);
        }

        $code = trim((string) $request->input('code'));

        if (!$this->twoFactor->verifyCode($secret, $code, $this->twoFactor->defaultWindow())) {
            return $this->render('setup', $actor, [
                'error'  => 'That code did not match. Check your authenticator app and try again.',
                'secret' => $this->twoFactor->formatSecret($secret),
                'qr'     => $this->twoFactor->provisioningQr(
                    $this->twoFactor->provisioningUri($user, $secret)
                ),
            ]);
        }

        // Codes are shown once, immediately after enrolment.
        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $this->mfa->enable(
            $actor['tenant_id'],
            $actor['user_id'],
            $this->twoFactor->encryptSecret($secret, $actor['tenant_id'], $actor['user_id'], (string) $user['email']),
            $this->twoFactor->recoveryDigests($recoveryCodes)
        );

        unset($_SESSION[self::SETUP_KEY]);

        Audit::log('user.mfa_enabled', 'user', $actor['user_id'], ['method' => 'totp']);

        return $this->render('codes', $actor, [
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request)
    {
        $this->guard();

        $actor = $this->actor();
        $user  = $this->userRow($actor);

        if (!$this->isEnabled($user)) {
            Response::abort(422, 'Two-factor authentication is not active on this account.');
        }

        if (!$this->confirmPassword($request, $user)) {
            return $this->render('enabled', $actor, [
                'error' => 'Your password is incorrect.',
            ]);
        }

        // Proof that the holder has the second factor too — otherwise a stolen
        // cookie alone could strip MFA off the account.
        $code  = trim((string) $request->input('code'));
        $valid = false;

        if ($code !== '') {
            try {
                $secret = $this->twoFactor->decryptSecret(
                    $user['mfa_secret'] ?? null,
                    $actor['tenant_id'],
                    $actor['user_id'],
                    (string) $user['email']
                );

                $valid = $this->twoFactor->verifyCode($secret, $code, $this->twoFactor->defaultWindow())
                    || $this->twoFactor->consumeRecoveryCode($actor['tenant_id'], $actor['user_id'], $code);
            } catch (\Throwable $e) {
                error_log('[MFA] disable could not unwrap the stored secret for user ' . $actor['user_id']);
            }
        }

        if (!$valid) {
            return $this->render('enabled', $actor, [
                'error' => 'Enter the current code from your app, or an unused recovery code.',
            ]);
        }

        $this->mfa->disable($actor['tenant_id'], $actor['user_id']);

        Audit::log('user.mfa_disabled', 'user', $actor['user_id']);

        return $this->render('enabled', $actor, [
            'saved'  => 'Two-factor authentication is turned off.',
            'recovery_remaining' => 0,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    private function render(string $state, array $actor, array $data = [])
    {
        return $this->view('Auth::two-factor-setup', array_merge([
            'title' => 'Two-Factor Authentication',
            'state' => $state,
        ], $data), 'app');
    }

    private function actor(): array
    {
        return [
            'tenant_id' => Tenant::require(),
            'user_id'   => (int) Auth::id(),
        ];
    }

    private function userRow(array $actor): array
    {
        $user = $this->mfa->findForUser($actor['tenant_id'], $actor['user_id']);

        if (!$user) {
            Response::abort(403, 'Your account is no longer active.');
        }

        return $user;
    }

    private function isEnabled(array $user): bool
    {
        return (int) ($user['mfa_enabled'] ?? 0) === 1
            && trim((string) ($user['mfa_secret'] ?? '')) !== '';
    }

    /**
     * Gate every self-service MFA action behind the configured permission —
     * never a role name. Off by default until the permission ships.
     */
    private function guard(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->twoFactor->featureEnabled()) {
            Response::abort(404);
        }

        if (!config('security.two_factor.require_permission')) {
            return;
        }

        $permission = (string) config('security.two_factor.permission');

        if ($permission === '' || !can($permission)) {
            Response::abort(403);
        }
    }

    /**
     * Park the enrolment secret for this user only, with a short TTL. A second
     * visit to setup() within the window reuses the secret so the QR does not
     * churn; after it expires a fresh one is generated.
     */
    private function beginSetup(array $actor, array $user): string
    {
        $pending = $_SESSION[self::SETUP_KEY] ?? null;

        if (
            is_array($pending)
            && (int) ($pending['user_id'] ?? 0) === $actor['user_id']
            && (int) ($pending['tenant_id'] ?? 0) === $actor['tenant_id']
            && (int) ($pending['expires_at'] ?? 0) > time()
            && !empty($pending['secret'])
        ) {
            return (string) $pending['secret'];
        }

        $secret = $this->twoFactor->generateSecret();

        $_SESSION[self::SETUP_KEY] = [
            'user_id'    => $actor['user_id'],
            'tenant_id'  => $actor['tenant_id'],
            'secret'     => $secret,
            'expires_at' => time() + $this->twoFactor->setupTtl(),
        ];

        return $secret;
    }

    private function pendingSecret(): ?string
    {
        $pending = $_SESSION[self::SETUP_KEY] ?? null;

        if (
            !is_array($pending)
            || (int) ($pending['user_id'] ?? 0) !== (int) Auth::id()
            || (int) ($pending['tenant_id'] ?? 0) !== Tenant::require()
            || (int) ($pending['expires_at'] ?? 0) < time()
            || empty($pending['secret'])
        ) {
            unset($_SESSION[self::SETUP_KEY]);

            return null;
        }

        return (string) $pending['secret'];
    }

    private function confirmPassword(Request $request, array $user): bool
    {
        $password = (string) $request->input('password');

        return $password !== ''
            && isset($user['password_hash'])
            && password_verify($password, (string) $user['password_hash']);
    }
}
