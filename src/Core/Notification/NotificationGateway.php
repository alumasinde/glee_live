<?php

namespace App\Core\Notification;

use App\Modules\Settings\Services\TenantSettingService;
use RuntimeException;

/**
 * Outbound transport for SMS and push.
 *
 * Providers are tenant configuration (tenant_settings keys "sms" / "push"), never
 * code: "null" driver = record-only (the message stays in notification_outbox as
 * 'skipped'), "webhook" driver = POST the message to a gateway endpoint you
 * control, which is how Twilio / FCM / Azure Notification Hubs get integrated
 * without coupling this app to their SDKs.
 */
final class NotificationGateway
{
    public const DRIVER_NULL    = 'null';
    public const DRIVER_WEBHOOK = 'webhook';

    public function __construct(private int $tenantId)
    {
    }

    public static function defaults(): array
    {
        return [
            'enabled'     => false,
            'driver'      => self::DRIVER_NULL,
            'endpoint'    => '',
            'api_key'     => '',
            'sender'      => '',
            'timeout'     => 10,
        ];
    }

    public function config(string $channel): array
    {
        if (!in_array($channel, [Channel::SMS, Channel::PUSH], true)) {
            throw new RuntimeException("No gateway configuration for channel '{$channel}'.");
        }

        $settings = (new TenantSettingService($this->tenantId))->get($channel, self::defaults());

        return array_merge(self::defaults(), $settings);
    }

    /**
     * Deliver, or throw with the provider's message so the caller can record the
     * failure and schedule a retry.
     */
    public function send(string $channel, string $recipient, string $title, string $body): void
    {
        $config = $this->config($channel);

        if (!$config['enabled']) {
            throw new RuntimeException(ucfirst($channel) . ' delivery is disabled for this tenant.');
        }

        if ($config['driver'] === self::DRIVER_NULL) {
            throw new RuntimeException('No ' . $channel . ' provider configured (driver=null).');
        }

        if ($config['driver'] !== self::DRIVER_WEBHOOK || trim((string) $config['endpoint']) === '') {
            throw new RuntimeException("Unsupported or incomplete {$channel} driver '{$config['driver']}'.");
        }

        $this->postWebhook($config, $channel, $recipient, $title, $body);
    }

    private function postWebhook(array $config, string $channel, string $recipient, string $title, string $body): void
    {
        $payload = json_encode([
            'channel'   => $channel,
            'recipient' => $recipient,
            'sender'    => (string) $config['sender'],
            'title'     => $title,
            'body'      => $body,
        ], JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen((string) $payload),
            'Accept: application/json',
        ];

        if ($config['api_key'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $config['api_key'];
        }

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'body'          => (string) $payload,
                'timeout'       => max(1, (int) $config['timeout']),
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($config['endpoint'], false, $context);

        if ($response === false) {
            $error = error_get_last();

            throw new RuntimeException(
                ucfirst($channel) . ' gateway request failed: ' . substr((string) ($error['message'] ?? 'unknown error'), 0, 300)
            );
        }

        $status = $http_response_header[0] ?? 'HTTP/?';

        if (!preg_match('#\s(2\d\d)\s#', (string) $status)) {
            throw new RuntimeException(
                ucfirst($channel) . ' gateway rejected the message: ' . $status . ' ' . substr($response, 0, 200)
            );
        }
    }
}
