<?php

namespace App\Core;

class Sanitizer
{
    /**
     * Sanitize a string for HTML output (XSS prevention)
     */
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize an array recursively
     */
    public static function escapeArray(array $data): array
    {
        return array_map(function ($value) {
            if (is_array($value)) {
                return self::escapeArray($value);
            }
            if (is_string($value)) {
                return self::escape($value);
            }
            return $value;
        }, $data);
    }

    /**
     * Sanitize email address
     */
    public static function email(string $email): string
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return strtolower(trim($email));
    }

    /**
     * Sanitize URL
     */
    public static function url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL);
    }

    /**
     * Sanitize integer
     */
    public static function int($value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitize float
     */
    public static function float($value): float
    {
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitize string (remove tags and special chars)
     */
    public static function string(string $value): string
    {
        return filter_var($value, FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    }

    /**
     * Sanitize for SQL (single quotes escape)
     * Note: This is a basic helper - always use prepared statements with PDO
     */
    public static function sql(string $value): string
    {
        return addslashes($value);
    }

    /**
     * Sanitize filename
     */
    public static function filename(string $filename): string
    {
        // Remove any characters that are not alphanumeric, hyphen, underscore, or dot
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        // Remove multiple dots
        $filename = preg_replace('/\.+/', '.', $filename);
        // Trim dots from start and end
        return trim($filename, '.');
    }

    /**
     * Sanitize phone number
     */
    public static function phone(string $phone): string
    {
        // Keep only digits, +, -, (, ), and spaces
        return preg_replace('/[^\d\+\-\(\)\s]/', '', $phone);
    }

    /**
     * Sanitize for safe HTML (allow certain tags)
     */
    public static function html(string $value, array $allowedTags = ['p', 'br', 'strong', 'em', 'u', 'ul', 'ol', 'li']): string
    {
        $allowed = implode('', array_map(fn($tag) => "<{$tag}>", $allowedTags));
        return strip_tags($value, $allowed);
    }

    /**
     * Remove potentially dangerous HTML/JS
     */
    public static function cleanHtml(string $value): string
    {
        // Remove script tags and their content
        $value = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value);
        // Remove on* event handlers
        $value = preg_replace('/\s*on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/is', '', $value);
        // Remove javascript: protocol
        $value = preg_replace('/javascript:/is', '', $value);
        // Remove data: protocol (except for images)
        $value = preg_replace('/data:(?!image\/)/is', '', $value);

        return $value;
    }

    /**
     * Sanitize for JSON output
     */
    public static function json($value): string
    {
        if (is_string($value)) {
            return self::escape($value);
        }
        if (is_array($value) || is_object($value)) {
            return json_encode(self::escapeArray((array)$value));
        }
        return json_encode($value);
    }

    /**
     * Sanitize array keys
     */
    public static function arrayKeys(array $array): array
    {
        $sanitized = [];
        foreach ($array as $key => $value) {
            $sanitizedKey = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
            $sanitized[$sanitizedKey] = $value;
        }
        return $sanitized;
    }

    /**
     * Remove null bytes
     */
    public static function removeNullBytes(string $value): string
    {
        return str_replace("\0", '', $value);
    }

    /**
     * Trim whitespace
     */
    public static function trim(string $value): string
    {
        return trim($value);
    }

    /**
     * Sanitize for CSV output
     */
    public static function csv(string $value): string
    {
        // Escape quotes and wrap in quotes if contains comma, quote, or newline
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            $value = str_replace('"', '""', $value);
            return '"' . $value . '"';
        }
        return $value;
    }

    /**
     * Sanitize for XML output
     */
    public static function xml(string $value): string
    {
        // Remove invalid XML characters
        $value = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value);
        // Escape XML special characters
        return htmlspecialchars($value, ENT_XML1, 'UTF-8');
    }

    /**
     * General sanitize (combine multiple sanitizers)
     */
    public static function clean(string $value, array $options = []): string
    {
        $options = array_merge([
            'trim' => true,
            'removeNullBytes' => true,
            'escape' => false,
            'cleanHtml' => false,
        ], $options);

        if ($options['removeNullBytes']) {
            $value = self::removeNullBytes($value);
        }

        if ($options['trim']) {
            $value = self::trim($value);
        }

        if ($options['cleanHtml']) {
            $value = self::cleanHtml($value);
        }

        if ($options['escape']) {
            $value = self::escape($value);
        }

        return $value;
    }

    /**
     * Sanitize request data (array or string)
     */
    public static function request($data)
    {
        if (is_array($data)) {
            return self::escapeArray($data);
        }
        if (is_string($data)) {
            return self::clean($data, ['escape' => true]);
        }
        return $data;
    }
}
