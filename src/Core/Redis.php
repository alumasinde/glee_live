<?php

namespace App\Core;

use Predis\Client as PredisClient;

class Redis
{
    private static ?PredisClient $client = null;
    private static bool $enabled = false;

    public static function connect(): ?PredisClient
    {
        if (self::$client !== null) {
            return self::$client;
        }

        self::$enabled = env('REDIS_ENABLED', false);

        if (!self::$enabled) {
            return null;
        }

        try {
            self::$client = new PredisClient([
                'scheme' => env('REDIS_SCHEME', 'tcp'),
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'password' => env('REDIS_PASSWORD', null),
                'database' => env('REDIS_DB', 0),
                'timeout' => env('REDIS_TIMEOUT', 5.0),
            ]);

            // Test connection
            self::$client->ping();

            return self::$client;
        } catch (\Throwable $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
            self::$enabled = false;
            return null;
        }
    }

    public static function isEnabled(): bool
    {
        if (self::$client === null) {
            self::connect();
        }
        return self::$enabled;
    }

    public static function get(string $key, $default = null)
    {
        if (!self::isEnabled()) {
            return self::sessionGet($key, $default);
        }

        $value = self::$client->get($key);
        return $value !== null ? json_decode($value, true) : $default;
    }

    public static function set(string $key, $value, int $ttl = null): bool
    {
        if (!self::isEnabled()) {
            return self::sessionSet($key, $value, $ttl);
        }

        $serialized = json_encode($value);
        if ($ttl !== null) {
            return self::$client->setex($key, $ttl, $serialized) === 'OK';
        }
        return self::$client->set($key, $serialized) === 'OK';
    }

    public static function delete(string $key): bool
    {
        if (!self::isEnabled()) {
            return self::sessionDelete($key);
        }

        return self::$client->del($key) > 0;
    }

    public static function exists(string $key): bool
    {
        if (!self::isEnabled()) {
            return self::sessionExists($key);
        }

        return self::$client->exists($key) > 0;
    }

    public static function increment(string $key, int $value = 1): int
    {
        if (!self::isEnabled()) {
            return self::sessionIncrement($key, $value);
        }

        return self::$client->incrby($key, $value);
    }

    public static function expire(string $key, int $seconds): bool
    {
        if (!self::isEnabled()) {
            return self::sessionExpire($key, $seconds);
        }

        return self::$client->expire($key, $seconds) > 0;
    }

    public static function ttl(string $key): int
    {
        if (!self::isEnabled()) {
            return self::sessionTtl($key);
        }

        return self::$client->ttl($key);
    }

    // Session fallback methods
    private static function sessionGet(string $key, $default = null)
    {
        $sessionKey = 'redis_fallback:' . $key;
        $data = $_SESSION[$sessionKey] ?? null;

        if ($data === null) {
            return $default;
        }

        // Check expiration
        if (isset($data['expires']) && time() > $data['expires']) {
            unset($_SESSION[$sessionKey]);
            return $default;
        }

        return $data['value'];
    }

    private static function sessionSet(string $key, $value, int $ttl = null): bool
    {
        $sessionKey = 'redis_fallback:' . $key;
        $_SESSION[$sessionKey] = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];
        return true;
    }

    private static function sessionDelete(string $key): bool
    {
        $sessionKey = 'redis_fallback:' . $key;
        $exists = isset($_SESSION[$sessionKey]);
        unset($_SESSION[$sessionKey]);
        return $exists;
    }

    private static function sessionExists(string $key): bool
    {
        $sessionKey = 'redis_fallback:' . $key;
        $data = $_SESSION[$sessionKey] ?? null;

        if ($data === null) {
            return false;
        }

        // Check expiration
        if (isset($data['expires']) && time() > $data['expires']) {
            unset($_SESSION[$sessionKey]);
            return false;
        }

        return true;
    }

    private static function sessionIncrement(string $key, int $value = 1): int
    {
        $sessionKey = 'redis_fallback:' . $key;
        $current = $_SESSION[$sessionKey]['value'] ?? 0;
        $_SESSION[$sessionKey]['value'] = $current + $value;
        return $_SESSION[$sessionKey]['value'];
    }

    private static function sessionExpire(string $key, int $seconds): bool
    {
        $sessionKey = 'redis_fallback:' . $key;
        if (!isset($_SESSION[$sessionKey])) {
            return false;
        }
        $_SESSION[$sessionKey]['expires'] = time() + $seconds;
        return true;
    }

    private static function sessionTtl(string $key): int
    {
        $sessionKey = 'redis_fallback:' . $key;
        $data = $_SESSION[$sessionKey] ?? null;

        if ($data === null || !isset($data['expires'])) {
            return -1;
        }

        $remaining = $data['expires'] - time();
        return max(-2, $remaining);
    }
}
