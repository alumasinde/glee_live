<?php

namespace App\Core;

/**
 * Environment reader shared by the web bootstrap and the bin/ CLI scripts.
 *
 * Dotenv returns everything as strings, so "false" would be truthy in PHP.
 * The literal casts happen once, here, so a CLI job and a web request read the
 * same value for the same key (previously each bin/ script kept its own
 * uncased copy, where REDIS_ENABLED=false was a truthy string).
 */
final class Env
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if ($value === null) {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}
