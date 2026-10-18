<?php

namespace App\Core;

class MailTemplate
{
    /**
     * Render an email template with data
     */
    public static function render(string $template, array $data = []): string
    {
        $templatePath = __DIR__ . '/../../resources/views/emails/' . $template . '.php';

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Email template not found: {$template}");
        }

        // Create a local scope for template variables
        $render = function() use ($templatePath, $data) {
            extract($data);
            ob_start();
            require $templatePath;
            return ob_get_clean();
        };

        return $render();
    }
}
