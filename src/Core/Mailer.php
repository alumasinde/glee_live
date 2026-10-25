<?php

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

final class Mailer
{
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /** Attempts before a failed email stops being retried by bin/notify-outbox.php. */
    public const MAX_ATTEMPTS = 5;

    public static function defaults(): array
    {
        return [
            'enabled'      => false,
            'host'         => '',
            'port'         => 587,
            'encryption'   => 'tls',
            'username'     => '',
            'password'     => '',
            'from_address' => '',
            'from_name'    => '',
            'insecure_tls' => false,
        ];
    }

    public static function config(int $tenantId): array
    {
        $stmt = DB::connect()->prepare("
            SELECT config_json
            FROM tenant_settings
            WHERE tenant_id   = :tenant_id
              AND setting_key = 'mail'
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $saved = $row ? (json_decode($row['config_json'], true) ?? []) : [];

        return array_merge(self::defaults(), $saved);
    }

    /**
     * Send (or skip) an email and always record the attempt in email_outbox.
     * Failed sends are stamped with a retry window so bin/notify-outbox.php can
     * pick them up. Returns the outbox status: sent | failed | skipped.
     */
    public static function send(int $tenantId, string $to, string $subject, string $htmlBody): string
    {
        $config = self::config($tenantId);

        $error    = null;
        $status   = self::STATUS_SKIPPED;
        $attempts = 0;

        if (empty($config['enabled']) || empty($config['host']) || empty($config['from_address'])) {
            $error = 'Mail not configured or disabled';
        } else {
            try {
                self::smtpSend($config, $to, $subject, $htmlBody);
                $status = self::STATUS_SENT;
            } catch (\Throwable $e) {
                $status   = self::STATUS_FAILED;
                $error    = substr($e->getMessage(), 0, 500);
                $attempts = 1;
            }
        }

        self::record($tenantId, $to, $subject, $htmlBody, $status, $error, $attempts);

        return $status;
    }

    /**
     * Retry a recorded failure in place — one outbox row per message, however
     * many attempts it takes. Used by the CLI worker; never throws.
     */
    public static function retry(int $outboxId, int $maxAttempts = self::MAX_ATTEMPTS): string
    {
        $row = self::find($outboxId);

        if (!$row || $row['status'] !== self::STATUS_FAILED) {
            return (string) ($row['status'] ?? self::STATUS_SKIPPED);
        }

        $attempts = (int) $row['attempts'];

        if ($attempts >= $maxAttempts) {
            return self::STATUS_FAILED;
        }

        $config = self::config((int) $row['tenant_id']);

        if (empty($config['enabled']) || empty($config['host']) || empty($config['from_address'])) {
            self::mark($outboxId, self::STATUS_FAILED, 'Mail not configured or disabled', $attempts + 1, null);

            return self::STATUS_FAILED;
        }

        try {
            self::smtpSend($config, (string) $row['to_email'], (string) $row['subject'], (string) $row['body']);
        } catch (\Throwable $e) {
            $next = $attempts + 1;
            self::mark(
                $outboxId,
                self::STATUS_FAILED,
                substr($e->getMessage(), 0, 500),
                $next,
                $next >= $maxAttempts ? null : self::backoffAt($next)
            );

            return self::STATUS_FAILED;
        }

        self::mark($outboxId, self::STATUS_SENT, null, $attempts + 1, null);

        return self::STATUS_SENT;
    }

    /** Exponential retry delay for the given attempt number (1-based). */
    public static function backoffMinutes(int $attempts): int
    {
        return 15 * (2 ** max(0, $attempts - 1));
    }

    private static function backoffAt(int $attempts): string
    {
        return date('Y-m-d H:i:s', time() + self::backoffMinutes($attempts) * 60);
    }

    private static function find(int $outboxId): ?array
    {
        $stmt = DB::connect()->prepare("
            SELECT tenant_id, to_email, subject, body, status, attempts
            FROM email_outbox
            WHERE id = ?
        ");

        $stmt->execute([$outboxId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private static function mark(int $outboxId, string $status, ?string $error, int $attempts, ?string $nextRetryAt): void
    {
        $stmt = DB::connect()->prepare("
            UPDATE email_outbox
            SET status        = :status,
                error         = :error,
                attempts      = :attempts,
                next_retry_at = :next_retry_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':status'        => $status,
            ':error'         => $error,
            ':attempts'      => $attempts,
            ':next_retry_at' => $nextRetryAt,
            ':id'            => $outboxId,
        ]);
    }

    private static function smtpSend(array $config, string $to, string $subject, string $htmlBody): void
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->Port       = (int) $config['port'];
        $mail->SMTPAuth   = $config['username'] !== '';
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];

        $encryption = strtolower($config['encryption'] ?? 'tls');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        }

        if (!empty($config['insecure_tls'])) {
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $mail->setFrom($config['from_address'], $config['from_name'] ?: $config['from_address']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->send();
    }

    private static function record(
        int    $tenantId,
        string $to,
        string $subject,
        string $body,
        string $status,
        ?string $error,
        int $attempts = 0
    ): void {
        $stmt = DB::connect()->prepare("
            INSERT INTO email_outbox
                (tenant_id, to_email, subject, body, status, error, attempts, next_retry_at)
            VALUES
                (:tenant_id, :to_email, :subject, :body, :status, :error, :attempts, :next_retry_at)
        ");

        $stmt->execute([
            ':tenant_id'     => $tenantId,
            ':to_email'      => $to,
            ':subject'       => $subject,
            ':body'          => $body,
            ':status'        => $status,
            ':error'         => $error,
            ':attempts'      => $attempts,
            ':next_retry_at' => $attempts > 0 ? self::backoffAt($attempts) : null,
        ]);
    }
}
