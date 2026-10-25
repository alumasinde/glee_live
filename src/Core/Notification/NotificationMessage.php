<?php

namespace App\Core\Notification;

/**
 * One notification, channel-agnostic. $body is plain text (used for in-app,
 * SMS and push); $html / $emailSubject are only used by the email channel.
 */
final class NotificationMessage
{
    public function __construct(
        public readonly string $eventKey,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $link = null,
        public readonly ?string $html = null,
        public readonly ?string $emailSubject = null,
        public readonly string $type = 'info',
    ) {
    }

    public function emailSubject(): string
    {
        return $this->emailSubject ?? $this->title;
    }

    public function emailHtml(): string
    {
        if ($this->html !== null) {
            return $this->html;
        }

        $html = nl2br(htmlspecialchars($this->body, ENT_QUOTES, 'UTF-8'));

        $link = $this->link
            ? '<p><a href="' . htmlspecialchars($this->link, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($this->link, ENT_QUOTES, 'UTF-8') . '</a></p>'
            : '';

        return '<h2>' . htmlspecialchars($this->title, ENT_QUOTES, 'UTF-8') . '</h2><p>' . $html . '</p>' . $link;
    }

    /** Short-form payload for push / SMS: title plus trimmed body. */
    public function shortBody(int $limit = 240): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $this->body) ?? $this->body);

        return mb_strlen($text) <= $limit ? $text : rtrim(mb_substr($text, 0, $limit - 1)) . '…';
    }
}
