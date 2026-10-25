<?php

namespace App\Modules\Auth\Services;

use App\Core\DB;
use App\Core\QrCode;
use PDO;

/**
 * TwoFactorService
 *
 * Self-contained RFC 6238 (TOTP) implementation — no Composer dependency was
 * added. HMAC-SHA1, 30-second step, 6 digits, +/-1 step window, compared with
 * hash_equals only. Also owns recovery codes (single use, stored as
 * password_hash digests) and the at-rest encryption of users.mfa_secret.
 *
 * PII/secret hygiene: this class never error_logs or returns a secret, an OTP
 * code or a recovery code into an audit payload. Plaintext codes exist only in
 * local variables and in the one-time enrolment response.
 */
class TwoFactorService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Unambiguous recovery-code alphabet: no I, O, 0 or 1. */
    private const RECOVERY_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const RECOVERY_GROUPS = [5, 5];

    private MfaSecretCipher $cipher;

    public function __construct(?MfaSecretCipher $cipher = null)
    {
        $this->cipher = $cipher ?? new MfaSecretCipher();
    }

    /*
    |--------------------------------------------------------------------------
    | CONFIG
    |--------------------------------------------------------------------------
    */

    public function digits(): int
    {
        return max(6, (int) ($this->config('two_factor.digits') ?? 6));
    }

    public function period(): int
    {
        return max(15, (int) ($this->config('two_factor.period') ?? 30));
    }

    public function algorithm(): string
    {
        return 'sha1';
    }

    public function defaultWindow(): int
    {
        $window = (int) ($this->config('two_factor.window') ?? 1);

        return $window >= 0 ? min($window, 3) : 1;
    }

    public function challengeTtl(): int
    {
        return max(60, (int) ($this->config('two_factor.challenge_ttl') ?? 300));
    }

    public function setupTtl(): int
    {
        return max(120, (int) ($this->config('two_factor.setup_ttl') ?? 900));
    }

    public function issuer(): string
    {
        $issuer = $this->config('two_factor.issuer');

        return is_string($issuer) && trim($issuer) !== '' ? trim($issuer) : 'GatePass';
    }

    public function featureEnabled(): bool
    {
        return (bool) ($this->config('two_factor.enabled') ?? true);
    }

    /*
    |--------------------------------------------------------------------------
    | SECRET GENERATION (base32)
    |--------------------------------------------------------------------------
    */

    /**
     * Cryptographically random shared secret, returned as a base32 string
     * (RFC 4648, unpadded) suitable for authenticator apps and otpauth URIs.
     */
    public function generateSecret(): string
    {
        $bytes = (int) ($this->config('two_factor.secret_bytes') ?? 20);
        $bytes = max(16, min($bytes, 32));

        return $this->base32Encode(random_bytes($bytes));
    }

    public function base32Encode(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $output     = '';
        $buffer     = 0;
        $bufferBits = 0;

        for ($i = 0, $length = strlen($binary); $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($binary[$i]);
            $bufferBits += 8;

            while ($bufferBits >= 5) {
                $bufferBits -= 5;
                $output .= self::BASE32_ALPHABET[($buffer >> $bufferBits) & 0x1F];
            }
        }

        if ($bufferBits > 0) {
            $output .= self::BASE32_ALPHABET[($buffer << (5 - $bufferBits)) & 0x1F];
        }

        return $output;
    }

    /**
     * Tolerant decoder: ignores case, spaces, dashes and '=' padding.
     */
    public function base32Decode(string $base32): string
    {
        $clean = strtoupper(preg_replace('/[\s=]+/', '', $base32) ?? '');

        if ($clean === '') {
            return '';
        }

        $map = array_flip(str_split(self::BASE32_ALPHABET));

        $buffer     = 0;
        $bufferBits = 0;
        $output     = '';

        for ($i = 0, $length = strlen($clean); $i < $length; $i++) {
            $char = $clean[$i];

            if (!isset($map[$char])) {
                return '';
            }

            $buffer = ($buffer << 5) | $map[$char];
            $bufferBits += 5;

            if ($bufferBits >= 8) {
                $bufferBits -= 8;
                $output .= chr(($buffer >> $bufferBits) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Normalize a user-typed secret to canonical uppercase base32 for display.
     */
    public function normalizeSecret(string $secret): string
    {
        return strtoupper(preg_replace('/[\s=]+/', '', $secret) ?? '');
    }

    /*
    |--------------------------------------------------------------------------
    | PROVISIONING (otpauth:// + QR)
    |--------------------------------------------------------------------------
    */

    /**
     * otpauth://totp URI per the Google Authenticator key-URI spec.
     *
     * @param array $user session/DB row shape: email, first_name, last_name, tenant_id
     */
    public function provisioningUri(array $user, string $secret, ?string $issuer = null): string
    {
        $issuer = $issuer ?? $this->issuer();

        $email = trim((string) ($user['email'] ?? ''));
        $name  = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        $label = $email !== '' ? $email : ($name !== '' ? $name : 'user');

        // Label is "issuer:account" — both halves percent-encoded.
        $label = rawurlencode($issuer) . ':' . rawurlencode($label);

        $query = http_build_query([
            'secret'    => $this->normalizeSecret($secret),
            'issuer'    => $issuer,
            'algorithm' => strtoupper($this->algorithm()),
            'digits'    => $this->digits(),
            'period'    => $this->period(),
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $query;
    }

    /**
     * QR as a data URI, reusing the app's existing bacon/bacon-qr-code helper.
     */
    public function provisioningQr(string $uri, int $size = 220): string
    {
        return QrCode::svgDataUri($uri, $size);
    }

    /**
     * Human-friendly secret groups: JBSW Y3DP EHPK 3PXP
     */
    public function formatSecret(string $secret): string
    {
        return implode(' ', str_split($this->normalizeSecret($secret), 4));
    }

    /*
    |--------------------------------------------------------------------------
    | TOTP VERIFY
    |--------------------------------------------------------------------------
    */

    /**
     * Verify a 6-digit code, allowing +/- $window time steps for clock drift.
     */
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $binary = $this->base32Decode($this->normalizeSecret($secret));

        if ($binary === '' || strlen($binary) < 10) {
            return false;
        }

        $code = preg_replace('/[\s-]+/', '', $code) ?? '';

        if (!preg_match('/^\d{' . $this->digits() . '}$/', $code)) {
            return false;
        }

        $window = max(0, min($window, 3));
        $step   = $this->period();
        $counter = intdiv(time(), $step);

        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = $this->otp($binary, $counter + $offset);

            if ($candidate !== null && hash_equals($candidate, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * RFC 6238/4226 dynamic truncation. Returns null rather than a partial code.
     */
    private function otp(string $binaryKey, int $counter): ?string
    {
        // 8-byte big-endian counter (pack('J') is 64-bit; guard negatives).
        if ($counter < 0) {
            return null;
        }

        $hash = hash_hmac($this->algorithm(), pack('J', $counter), $binaryKey, true);

        if (strlen($hash) < 20) {
            return null;
        }

        $offset      = ord($hash[strlen($hash) - 1]) & 0x0F;
        $truncated   = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ((ord($hash[$offset + 3]) & 0xFF))
        );

        $digits = $this->digits();

        return str_pad((string) ($truncated % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | RECOVERY CODES
    |--------------------------------------------------------------------------
    */

    /**
     * @return string[] readable single-use codes, e.g. "K7QPM-3XRBW"
     */
    public function generateRecoveryCodes(): array
    {
        $count = (int) ($this->config('two_factor.recovery_codes') ?? 8);
        $count = max(4, min($count, 16));

        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = $this->randomRecoveryCode();
        }

        return $codes;
    }

    private function randomRecoveryCode(): string
    {
        $alphabet = self::RECOVERY_ALPHABET;
        $max      = strlen($alphabet) - 1;
        $parts    = [];

        foreach (self::RECOVERY_GROUPS as $length) {
            $group = '';

            for ($i = 0; $i < $length; $i++) {
                $group .= $alphabet[random_int(0, $max)];
            }

            $parts[] = $group;
        }

        return implode('-', $parts);
    }

    /**
     * Only digests are ever persisted. Returns the JSON for
     * users.mfa_recovery_codes.
     *
     * @param string[] $codes
     */
    public function hashRecoveryCodes(array $codes): string
    {
        return json_encode(
            $this->recoveryDigests($codes),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );
    }

    /**
     * @param string[] $codes
     * @return string[] password_hash digests, never the codes themselves
     */
    public function recoveryDigests(array $codes): array
    {
        $digests = [];

        foreach ($codes as $code) {
            $normalized = $this->normalizeRecoveryCode($code);

            if ($normalized === '') {
                continue;
            }

            $digests[] = password_hash($normalized, PASSWORD_DEFAULT);
        }

        return $digests;
    }

    public function normalizeRecoveryCode(string $code): string
    {
        $code = strtoupper(preg_replace('/[\s]+/', '', $code) ?? '');

        return preg_match('/^[A-Z2-9]{5}-?[A-Z2-9]{5}$/', $code) ? $code : '';
    }

    /**
     * Decode the JSON digest column defensively.
     */
    public function decodeRecoveryDigests(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Never log the column contents.
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(
            $decoded,
            static fn ($digest): bool => is_string($digest) && strlen($digest) >= 20
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | SECRET PERSISTENCE
    |--------------------------------------------------------------------------
    */

    public function encryptSecret(string $secret, int $tenantId, int $userId, string $email): string
    {
        return $this->cipher->encrypt($secret, $tenantId, $userId, $email);
    }

    public function decryptSecret(?string $payload, int $tenantId, int $userId, string $email): string
    {
        return $this->cipher->decrypt($payload, $tenantId, $userId, $email);
    }

    public function usesApplicationKey(): bool
    {
        return $this->cipher->usesApplicationKey();
    }

    /*
    |--------------------------------------------------------------------------
    | RECOVERY CODE CONSUMPTION (atomic)
    |--------------------------------------------------------------------------
    */

    /**
     * Match a recovery code and delete the used digest in the same transaction,
     * with the row locked so two concurrent requests cannot spend one code.
     */
    public function consumeRecoveryCode(int $tenantId, int $userId, string $code): bool
    {
        $normalized = $this->normalizeRecoveryCode($code);

        if ($normalized === '') {
            return false;
        }

        $digests = null;

        $matched = DB::transaction(function (PDO $pdo) use ($tenantId, $userId, $normalized, &$digests): bool {
            $stmt = $pdo->prepare("
                SELECT mfa_recovery_codes
                FROM users
                WHERE id = :user_id
                  AND tenant_id = :tenant_id
                  AND is_active = 1
                FOR UPDATE
            ");

            $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);

            if ($stmt->rowCount() !== 1) {
                return false;
            }

            $digests = $this->decodeRecoveryDigests($stmt->fetchColumn() ?: null);

            if ($digests === []) {
                return false;
            }

            $matchedIndex = null;

            foreach ($digests as $index => $digest) {
                if (password_verify($normalized, (string) $digest)) {
                    $matchedIndex = $index;
                    break;
                }
            }

            if ($matchedIndex === null) {
                return false;
            }

            // Re-index so the JSON stays a clean list, then persist the
            // filtered set inside this same transaction.
            unset($digests[$matchedIndex]);
            $remaining = array_values($digests);

            $update = $pdo->prepare("
                UPDATE users
                SET mfa_recovery_codes = :codes
                WHERE id = :user_id
                  AND tenant_id = :tenant_id
                  AND is_active = 1
            ");

            $update->execute([
                ':codes'     => json_encode($remaining, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                ':user_id'   => $userId,
                ':tenant_id' => $tenantId,
            ]);

            return $update->rowCount() === 1;
        });

        return $matched === true;
    }

    public function remainingRecoveryCodes(int $tenantId, int $userId): int
    {
        $stmt = DB::connect()->prepare("
            SELECT mfa_recovery_codes
            FROM users
            WHERE id = :user_id
              AND tenant_id = :tenant_id
            LIMIT 1
        ");

        $stmt->execute([':user_id' => $userId, ':tenant_id' => $tenantId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return 0;
        }

        return count($this->decodeRecoveryDigests($row['mfa_recovery_codes'] ?? null));
    }

    private function config(string $key): mixed
    {
        return function_exists('config') ? config('security.' . $key) : null;
    }
}
