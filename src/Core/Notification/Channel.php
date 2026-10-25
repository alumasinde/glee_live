<?php

namespace App\Core\Notification;
final class Channel
{
    public const EMAIL  = 'email';
    public const SMS    = 'sms';
    public const PUSH   = 'push';
    public const IN_APP = 'in_app';

    /** @var list<string> */
    public const ALL = [self::EMAIL, self::SMS, self::PUSH, self::IN_APP];

    public static function label(string $channel): string
    {
        return match ($channel) {
            self::EMAIL  => 'Email',
            self::SMS    => 'SMS',
            self::PUSH   => 'App push',
            self::IN_APP => 'In-app',
            default      => ucfirst($channel),
        };
    }

    public static function defaultEnabled(string $channel): bool
    {
        return in_array($channel, [self::EMAIL, self::IN_APP], true);
    }
}
