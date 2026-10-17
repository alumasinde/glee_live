<?php

namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

final class Mailer
{
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';
    public const STATUS_SKIPPED = 'skipped';

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
     * Returns the outbox status: sent | failed | skipped.
     */
    public static function send(int $tenantId, string $to, string $subject, string $htmlBody): string
    {
        $config = self::config($tenantId);

        $error  = null;
        $status = self::STATUS_SKIPPED;

        if (empty($config['enabled']) || empty($config['host']) || empty($config['from_address'])) {
            $error = 'Mail not configured or disabled';
        } else {
            try {
                self::smtpSend($config, $to, $subject, $htmlBody);
                $status = self::STATUS_SENT;
            } catch (\Throwable $e) {
                $status = self::STATUS_FAILED;
                $error  = substr($e->getMessage(), 0, 500);
            }
        }

        self::record($tenantId, $to, $subject, $htmlBody, $status, $error);

        return $status;
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
        ?string $error
    ): void {
        $stmt = DB::connect()->prepare("
            INSERT INTO email_outbox
                (tenant_id, to_email, subject, body, status, error)
            VALUES
                (:tenant_id, :to_email, :subject, :body, :status, :error)
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':to_email'  => $to,
            ':subject'   => $subject,
            ':body'      => $body,
            ':status'    => $status,
            ':error'     => $error,
        ]);
    }
}
