<?php

namespace App\Modules\Auth\Services;

/**
 * MfaSecretCipher
 *
 * Encrypts users.mfa_secret at rest with AES-256-GCM. NEVER returns plaintext
 * to a log, an audit payload or a view; the only plaintext copies live in
 * $_SESSION during the short enrolment window.
 *
 * Key material resolution (see config/security.php > two_factor.encryption):
 *
 *   mode "k" — a real application key exists (env APP_KEY or MFA_ENC_KEY).
 *              HKDF-SHA256(masterKey, info="auth-mfa-secret.v1", per-user
 *              context salt) gives one independent 256-bit key per user, so a
 *              leaked row cannot be brute forced from another row's key.
 *
 *   mode "d" — NO application key is configured anywhere in this project
 *              (verified: config/app.php has no 'key', .env / .env.example have
 *              no APP_KEY, nothing else in src/ uses openssl_*). Rather than
 *              store the TOTP secret in the clear we derive per-user key
 *              material with PBKDF2-SHA256 over
 *              pepper|email, salted by "mfa.v1|<tenant_id>|<user_id>".
 *
 *              TRADEOFF, stated plainly: every input of that derivation except
 *              the pepper lives in the same database as the ciphertext, so
 *              "dump protection" only. A read of users.mfa_secret alone
 *              (SQL injection, leaked backup, read-only replica) is useless,
 *              but an attacker who has BOTH the full row set and the source
 *              tree — in practice the config/security.php pepper — can decrypt
 *              every enrolled secret. Set APP_KEY (or MFA_ENC_KEY) in .env and
 *              re-save the pepper to move to mode "k"; secrets already stored
 *              in mode "d" will then fail to decrypt and each user must
 *              re-enrol (fail-closed by design, see decrypt()).
 *
 * Ciphertext layout (fits varchar(255), ~68 bytes for a 20-byte secret):
 *
 *   v1.<mode>.base64( iv(12) | tag(16) | ciphertext )
 *
 * The tenant id + user id are bound into GCM's additional authenticated data,
 * so a ciphertext cannot be transplanted from one users row to another.
 */
class MfaSecretCipher
{
    private const VERSION = 'v1';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private const MODE_ENV_KEY = 'k';
    private const MODE_DERIVED = 'd';

    private string $info = 'glee_live.auth.mfa_secret.v1';

    /**
     * Encrypt and return the storable payload for users.mfa_secret.
     */
    public function encrypt(?string $plaintext, int $tenantId, int $userId, string $email): string
    {
        if ($plaintext === null || $plaintext === '') {
            throw new \RuntimeException('Cannot encrypt an empty TOTP secret.');
        }

        [$mode, $key] = $this->resolveKey($tenantId, $userId, $email);

        $iv = random_bytes(self::IV_LENGTH);

        $ciphertext = openssl_encrypt(
            $plaintext,
            $this->cipher(),
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $this->aad($tenantId, $userId),
            self::TAG_LENGTH
        );

        if ($ciphertext === false || $tag === null) {
            throw new \RuntimeException('Unable to encrypt the TOTP secret.');
        }

        return self::VERSION . '.' . $mode . '.' . base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * Recover the TOTP secret. Throws on any tampering, key rotation or
     * mode change — callers must fail closed (no login, no fallback to
     * plaintext) and the user re-enrols or uses a recovery code.
     */
    public function decrypt(?string $payload, int $tenantId, int $userId, string $email): string
    {
        if ($payload === null || trim($payload) === '') {
            throw new \RuntimeException('No TOTP secret is stored for this account.');
        }

        $parts = explode('.', $payload, 3);

        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            throw new \RuntimeException('Stored TOTP secret is malformed.');
        }

        [$version, $mode, $body] = $parts;

        $raw = base64_decode($body, true);

        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            throw new \RuntimeException('Stored TOTP secret is malformed.');
        }

        $iv         = substr($raw, 0, self::IV_LENGTH);
        $tag        = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        // Mode "d" payloads stay readable while no app key is configured; as
        // soon as APP_KEY appears we refuse mode "d" so a silently downgraded
        // or replayed payload cannot be accepted.
        if ($mode === self::MODE_ENV_KEY && $this->currentMode() !== self::MODE_ENV_KEY) {
            throw new \RuntimeException('Stored TOTP secret was wrapped with a key that is no longer configured.');
        }

        if ($mode === self::MODE_DERIVED && $this->currentMode() === self::MODE_ENV_KEY) {
            throw new \RuntimeException('Stored TOTP secret predates the configured application key; re-enrol two-factor.');
        }

        $key = $this->keyFor($mode, $tenantId, $userId, $email);

        $plaintext = openssl_decrypt(
            $ciphertext,
            $this->cipher(),
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $this->aad($tenantId, $userId)
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Unable to decrypt the stored TOTP secret.');
        }

        return $plaintext;
    }

    /**
     * True when a real application key is configured (mode "k").
     * Surfaced so the enrolment view can warn about the weaker mode.
     */
    public function usesApplicationKey(): bool
    {
        return $this->currentMode() === self::MODE_ENV_KEY;
    }

    /**
     * @return string 'k' when APP_KEY/MFA_ENC_KEY exists, otherwise 'd'
     */
    private function currentMode(): string
    {
        return $this->masterKey() !== null ? self::MODE_ENV_KEY : self::MODE_DERIVED;
    }

    /**
     * @return array{0:string,1:string} [mode, 32-byte key]
     */
    private function resolveKey(int $tenantId, int $userId, string $email): array
    {
        $mode = $this->currentMode();

        return [$mode, $this->keyFor($mode, $tenantId, $userId, $email)];
    }

    /**
     * @return string 32-byte key for the requested mode
     */
    private function keyFor(string $mode, int $tenantId, int $userId, string $email): string
    {
        return match ($mode) {
            self::MODE_ENV_KEY => $this->derivedFromMaster($tenantId, $userId, $email),
            self::MODE_DERIVED => $this->derivedFromRow($tenantId, $userId, $email),
            default            => throw new \RuntimeException('Unknown secret encryption mode.'),
        };
    }

    private function derivedFromMaster(int $tenantId, int $userId, string $email): string
    {
        $key = hash_hkdf(
            $this->kdf(),
            (string) $this->masterKey(),
            32,
            $this->info,
            $this->aad($tenantId, $userId) . '|' . $email
        );

        if (strlen($key) !== 32) {
            throw new \RuntimeException('Unable to derive the TOTP secret key.');
        }

        return $key;
    }

    private function derivedFromRow(int $tenantId, int $userId, string $email): string
    {
        $key = hash_pbkdf2(
            $this->kdf(),
            $this->pepper() . '|' . $email,
            'mfa.v1|' . $tenantId . '|' . $userId,
            $this->iterations(),
            32,
            true
        );

        if (strlen($key) !== 32) {
            throw new \RuntimeException('Unable to derive the TOTP secret key.');
        }

        return $key;
    }

    private function masterKey(): ?string
    {
        foreach (['MFA_ENC_KEY', 'APP_KEY'] as $variable) {
            $value = env($variable);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function aad(int $tenantId, int $userId): string
    {
        return 't' . $tenantId . ':u' . $userId;
    }

    private function cipher(): string
    {
        $cipher = config('security.two_factor.encryption.cipher');

        return is_string($cipher) && $cipher !== '' ? $cipher : 'aes-256-gcm';
    }

    private function kdf(): string
    {
        $kdf = config('security.two_factor.encryption.kdf');

        return in_array($kdf, hash_algos(), true) ? (string) $kdf : 'sha256';
    }

    private function iterations(): int
    {
        $iterations = (int) config('security.two_factor.encryption.iterations');

        return $iterations >= 1000 ? $iterations : 100000;
    }

    /**
     * The out-of-band secret that makes mode "d" more than obfuscation.
     * Ships in config/security.php, so an install that never changes it gets a
     * loud debug log (message contains no key material).
     */
    private function pepper(): string
    {
        $pepper = config('security.two_factor.encryption.pepper');

        if (!is_string($pepper) || $pepper === '') {
            throw new \RuntimeException('MFA encryption pepper is missing.');
        }

        if ($pepper === 'change-me-mfa-pepper') {
            error_log('[MFA] two_factor.encryption.pepper is still the shipped default; set MFA_SECRET_PEPPER or APP_KEY in .env.');
        }

        return $pepper;
    }
}
