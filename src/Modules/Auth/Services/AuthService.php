<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Tenant\Repositories\TenantRepository;
use App\Core\Mailer;
use RuntimeException;

class AuthService
{
    /**
     * Outcome of a password check. Distinct statuses let the controller choose
     * a redirect without ever revealing whether the password was correct.
     */
    public const STATUS_LOGIN        = 'login';         // session established
    public const STATUS_MFA_REQUIRED = 'mfa_required';  // stash set, no session
    public const STATUS_FAILED       = 'failed';        // generic 'Invalid credentials.'
    public const STATUS_LOCKED       = 'locked';        // generic 'Try again later.'

    /** Session key holding the pre-authentication two-factor stash. */
    public const MFA_SESSION_KEY = 'mfa_pending';

    /**
     * Fixed bcrypt digest burned when the account does not exist, so an
     * unknown-email response is not measurably faster than a wrong password.
     */
    private const TIMING_HASH =
        '$2y$12$KbQv3eJ6WVDrTwLr7TdXMuykcKmzjoQAgJqfu8Q.neovORi1rFBci';

    private UserRepository $users;

    private TenantRepository $tenants;

    private Mailer $mailer;

    private TwoFactorService $twoFactor;

public function __construct(
    ?UserRepository $users = null,
    ?TenantRepository $tenants = null,
    ?Mailer $mailer = null,
    ?TwoFactorService $twoFactor = null
) {
    $this->users = $users ?? new UserRepository();
    $this->tenants = $tenants ?? new TenantRepository();
    $this->mailer = $mailer ?? new Mailer();
    $this->twoFactor = $twoFactor ?? new TwoFactorService();
}


    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */
    /**
     * Backwards-compatible boolean login (tests and older callers).
     *
     * @return bool true ONLY when a session was fully established. An account
     *              with two-factor enabled returns false here, exactly like a
     *              failed login, and never populates $_SESSION['user'].
     */
    public function attempt(
        int $tenantId,
        string $email,
        string $password
    ): bool {
        return $this->login($tenantId, $email, $password)['status'] === self::STATUS_LOGIN;
    }

    /**
     * Password step + DB lockout + two-factor hand-off.
     *
     * @return array{status:string, user?:array}
     */
    public function login(int $tenantId, string $email, string $password): array
    {
        $user = $this->users->findForLogin($tenantId, $email);

        // Unknown user: burn the same CPU as a real verify, no row to
        // increment (the IP rate limiter covers that case), generic result.
        if (!$user) {
            password_verify($password, self::TIMING_HASH);

            return ['status' => self::STATUS_FAILED];
        }

        $userId = (int) $user['id'];

        // Locked account: refuse before the password step so a locked-out user
        // cannot keep guessing, and the counter stays frozen until it expires.
        if ($this->isLocked($user)) {
            return ['status' => self::STATUS_LOCKED];
        }

        if (!password_verify($password, $user['password_hash'])) {
            $this->users->registerFailedAttempt(
                $tenantId,
                $userId,
                $this->lockoutMaxAttempts(),
                $this->lockoutMinutes()
            );

            return ['status' => self::STATUS_FAILED];
        }

        // Opportunistic password rehash (algorithm upgrades)
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->users->updatePassword(
                (int) $user['tenant_id'],
                $userId,
                $newHash
            );
        }

        // Password verified: clear the lockout counters and stamp
        // tokens_invalid_before = NOW() so a later session-validity check has a
        // database basis (compare it against $_SESSION['auth_at']).
        $this->users->recordLoginSuccess($tenantId, $userId);

        if ($this->twoFactorRequired($user)) {
            $this->stashPendingChallenge($tenantId, $userId);

            return ['status' => self::STATUS_MFA_REQUIRED];
        }

        $this->completeLogin($user);

        return ['status' => self::STATUS_LOGIN, 'user' => $user];
    }

    /*
    |--------------------------------------------------------------------------
    | SESSION ESTABLISHMENT (single code path)
    |--------------------------------------------------------------------------
    */

    /**
     * The ONLY place identity is written to the session. Both the plain-password
     * login and a successful two-factor challenge end here, so regeneration and
     * the $_SESSION['user'] shape stay byte-identical to the pre-MFA behaviour.
     */
    public function completeLogin(array $user): void
    {
        // Prevent session fixation
        session_regenerate_id(true);

        unset($_SESSION[self::MFA_SESSION_KEY]);

        $_SESSION['user'] = [
            'id'            => (int) $user['id'],
            'tenant_id'     => (int) $user['tenant_id'],
            'tenant_code'   => $user['tenant_code'],
            'email'         => $user['email'],
            'first_name'    => $user['first_name'],
            'last_name'     => $user['last_name'],
            'role'          => $user['role'],
            'tenant_name'   => $user['tenant_name'],
            'tenant_logo'   => $user['tenant_logo'],
        ];

        // Basis for the future session-invalidity check against
        // users.tokens_invalid_before; deliberately a sibling key so the
        // $_SESSION['user'] shape above is unchanged.
        $_SESSION['auth_at'] = time();
    }

    public function twoFactorRequired(array $user): bool
    {
        if (!$this->twoFactor->featureEnabled()) {
            return false;
        }

        return (int) ($user['mfa_enabled'] ?? 0) === 1
            && trim((string) ($user['mfa_secret'] ?? '')) !== '';
    }

    /*
    |--------------------------------------------------------------------------
    | TWO-FACTOR CHALLENGE (pre-authentication)
    |--------------------------------------------------------------------------
    */

    /**
     * Park an identity that verified its password but has not logged in yet.
     * No secret material is stored: no password, no TOTP secret, no code.
     */
    public function stashPendingChallenge(int $tenantId, int $userId): void
    {
        $_SESSION[self::MFA_SESSION_KEY] = [
            'user_id'    => $userId,
            'tenant_id'  => $tenantId,
            'expires_at' => time() + $this->twoFactor->challengeTtl(),
        ];
    }

    /**
     * @return array{user_id:int, tenant_id:int}|null null when absent or expired
     */
    public function pendingChallenge(): ?array
    {
        $pending = $_SESSION[self::MFA_SESSION_KEY] ?? null;

        if (
            !is_array($pending)
            || empty($pending['user_id'])
            || empty($pending['tenant_id'])
            || (int) ($pending['expires_at'] ?? 0) < time()
        ) {
            unset($_SESSION[self::MFA_SESSION_KEY]);

            return null;
        }

        return [
            'user_id'   => (int) $pending['user_id'],
            'tenant_id' => (int) $pending['tenant_id'],
        ];
    }

    public function clearPendingChallenge(): void
    {
        unset($_SESSION[self::MFA_SESSION_KEY]);
    }

    /**
     * Verify the OTP (or a single-use recovery code) for a parked challenge.
     *
     * Every rejection returns the same generic message, so the challenge never
     * reveals that the password step already succeeded, and every failure
     * feeds the same failed_attempts / locked_until machinery as the password
     * step — an OTP guesser hits the account lockout too.
     *
     * @return array{status:string, user?:array, method?:string}
     */
    public function verifyChallenge(string $code): array
    {
        $pending = $this->pendingChallenge();

        if ($pending === null) {
            return ['status' => self::STATUS_FAILED];
        }

        $tenantId = $pending['tenant_id'];
        $userId   = $pending['user_id'];

        // Re-read the row: covers an account disabled between the password step
        // and this one, and picks up a lockout applied by another request.
        $user = $this->users->findForLoginById($tenantId, $userId);

        if (!$user || (int) $user['mfa_enabled'] !== 1) {
            $this->clearPendingChallenge();

            return ['status' => self::STATUS_FAILED];
        }

        if ($this->isLocked($user)) {
            $this->clearPendingChallenge();

            return ['status' => self::STATUS_LOCKED];
        }

        $method = null;

        if ($this->checkTotp($user, $code)) {
            $method = 'totp';
        } elseif ($this->twoFactor->consumeRecoveryCode($tenantId, $userId, $code)) {
            $method = 'recovery';
        }

        if ($method !== null) {
            $this->users->recordLoginSuccess($tenantId, $userId);
            $this->completeLogin($user);

            return ['status' => self::STATUS_LOGIN, 'user' => $user, 'method' => $method];
        }

        $this->users->registerFailedAttempt(
            $tenantId,
            $userId,
            $this->lockoutMaxAttempts(),
            $this->lockoutMinutes()
        );

        if ($this->isLocked($this->users->findForLoginById($tenantId, $userId))) {
            $this->clearPendingChallenge();

            return ['status' => self::STATUS_LOCKED];
        }

        return ['status' => self::STATUS_FAILED];
    }

    /**
     * Unwrap the stored secret and match the code inside the drift window.
     * Fail-closed: an undecryptable secret means "no valid code", never a
     * bypass, and no key material ever reaches the logs.
     */
    private function checkTotp(array $user, string $code): bool
    {
        if (trim($code) === '') {
            return false;
        }

        try {
            $secret = $this->twoFactor->decryptSecret(
                $user['mfa_secret'] ?? null,
                (int) $user['tenant_id'],
                (int) $user['id'],
                (string) $user['email']
            );
        } catch (\Throwable $e) {
            // Metadata is an id only — never the secret, the code or the email.
            error_log('[MFA] stored TOTP secret could not be unwrapped for user ' . (int) $user['id']);

            return false;
        }

        return $this->twoFactor->verifyCode(
            $secret,
            $code,
            $this->twoFactor->defaultWindow()
        );
    }

    private function isLocked(?array $user): bool
    {
        if ($user === null) {
            return false;
        }

        $lockedUntil = $user['locked_until'] ?? null;

        if (!is_string($lockedUntil) || trim($lockedUntil) === '') {
            return false;
        }

        $timestamp = strtotime($lockedUntil);

        return $timestamp !== false && $timestamp > time();
    }

    private function lockoutMaxAttempts(): int
    {
        $max = (int) ($this->config('security.lockout.max_attempts') ?? 5);

        return $max >= 1 ? $max : 5;
    }

    private function lockoutMinutes(): int
    {
        $minutes = (int) ($this->config('security.lockout.minutes') ?? 15);

        return $minutes >= 1 ? $minutes : 15;
    }

    private function config(string $key): mixed
    {
        return function_exists('config') ? config($key) : null;
    }

public function getTenantContext(int $id): array
{
    $tenant = $this->tenants->findTenantLogo($id);

    if (!$tenant) {
        throw new RuntimeException("Tenant not found");
    }

    return $tenant;
}
    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]
            );
        }

        session_destroy();
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD RESET REQUEST (Tenant-Aware)
    |--------------------------------------------------------------------------
    */
    public function createResetTokenByEmail(
        int $tenantId,
        string $email
    ): void {

        $user = $this->users->findActiveByEmail($tenantId, $email);

        // Always fail silently (prevent enumeration)
        if (!$user) {
            return;
        }

        $expiresAt = new \DateTimeImmutable('+1 hour');

        $rawToken = $this->users->createPasswordResetToken(
            (int) $user['id'],
            $expiresAt
        );

        // Send password reset email
        $this->sendPasswordResetEmail($tenantId, $email, $rawToken);
    }

    private function sendPasswordResetEmail(int $tenantId, string $email, string $token): void
    {
        $resetUrl = config('app.url') . '/reset-password?token=' . $token;
        $appName = config('app.name', 'Gatepass Management System');

        // Render email template from view file
        $htmlBody = \App\Core\MailTemplate::render('password-reset', [
            'resetUrl' => $resetUrl,
            'appName' => $appName,
        ]);

        $subject = 'Password Reset Request';

        try {
            Mailer::send($tenantId, $email, $subject, $htmlBody);
        } catch (\Throwable $e) {
            // Log error but don't expose to user (prevent enumeration)
            error_log('Password reset email failed: ' . $e->getMessage());
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD RESET (Atomic + Tenant Safe)
    |--------------------------------------------------------------------------
    */
    public function resetPassword(
        int $tenantId,
        string $rawToken,
        string $newPassword
    ): array {

        self::assertStrongPassword($newPassword);

        $user = $this->users->findByValidResetToken(
            $tenantId,
            $rawToken
        );

        if (!$user) {
            throw new RuntimeException('Invalid or expired token.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Atomic update (prevents token replay)
        $this->users->resetPasswordWithToken(
            $tenantId,
            (int) $user['id'],
            $rawToken,
            $newHash
        );

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD POLICY
    |--------------------------------------------------------------------------
    */
    public static function assertStrongPassword(string $password): void
    {
        $policy = (array) (function_exists('config') ? config('security.password') : null);

        // Floor of 8: a mistyped PASSWORD_MIN_LENGTH must not silently weaken
        // every account created from then on.
        $minLength = max(8, (int) ($policy['min_length'] ?? 12));

        if (strlen($password) < $minLength) {
            throw new RuntimeException("Password must be at least {$minLength} characters.");
        }

        $missing = [];

        if (($policy['require_uppercase'] ?? true) && ! preg_match('/[A-Z]/', $password)) {
            $missing[] = 'uppercase';
        }

        if (($policy['require_lowercase'] ?? true) && ! preg_match('/[a-z]/', $password)) {
            $missing[] = 'lowercase';
        }

        if (($policy['require_numbers'] ?? true) && ! preg_match('/[0-9]/', $password)) {
            $missing[] = 'numeric';
        }

        if (($policy['require_special_chars'] ?? false) && ! preg_match('/[^A-Za-z0-9]/', $password)) {
            $missing[] = 'special';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                'Password must contain ' . implode(', ', $missing) . ' characters.'
            );
        }
    }
}