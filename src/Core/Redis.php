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
            return self::storeGet($key, $default);
        }

        $value = self::$client->get($key);
        return $value !== null ? json_decode($value, true) : $default;
    }

    public static function set(string $key, $value, int $ttl = null): bool
    {
        if (!self::isEnabled()) {
            return self::storeSet($key, $value, $ttl);
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
            return self::storeDelete($key);
        }

        return self::$client->del($key) > 0;
    }

    public static function exists(string $key): bool
    {
        if (!self::isEnabled()) {
            return self::storeExists($key);
        }

        return self::$client->exists($key) > 0;
    }

    public static function increment(string $key, int $value = 1): int
    {
        if (!self::isEnabled()) {
            return self::storeIncrement($key, $value);
        }

        return self::$client->incrby($key, $value);
    }

    public static function expire(string $key, int $seconds): bool
    {
        if (!self::isEnabled()) {
            return self::storeExpire($key, $seconds);
        }

        return self::$client->expire($key, $seconds) > 0;
    }

    public static function ttl(string $key): int
    {
        if (!self::isEnabled()) {
            return self::storeTtl($key);
        }

        return self::$client->ttl($key);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Fallback store (no Redis configured)
    //
    // Previously this lived in $_SESSION, which made every caller per-browser:
    // counters vanished when the client dropped its cookie, and the gate device
    // endpoint — which sends no cookie at all — got a fresh bucket per request,
    // so its rate limit never applied. One file per key inside var/cache/kv is
    // shared by all PHP workers and needs no extra service.
    // ─────────────────────────────────────────────────────────────────────────

    private static function storeDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/var/cache/kv';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function storePath(string $key): string
    {
        return self::storeDir() . '/' . hash('sha256', $key) . '.json';
    }

    /** @return array{value: mixed, expires: int|null}|null */
    private static function storeRead(string $key): ?array
    {
        $path = self::storePath($key);

        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);

        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);

        if (!is_array($data) || !array_key_exists('value', $data)) {
            return null;
        }

        $expires = $data['expires'] ?? null;
        $expires = is_int($expires) ? $expires : null;

        if ($expires !== null && time() > $expires) {
            @unlink($path);
            return null;
        }

        return ['value' => $data['value'], 'expires' => $expires];
    }

    private static function storeWrite(string $key, $value, ?int $expires): bool
    {
        $payload = json_encode(['value' => $value, 'expires' => $expires]);

        if ($payload === false) {
            return false;
        }

        return @file_put_contents(self::storePath($key), $payload, LOCK_EX) !== false;
    }

    private static function storeGet(string $key, $default = null)
    {
        $data = self::storeRead($key);

        return $data === null ? $default : $data['value'];
    }

    private static function storeSet(string $key, $value, ?int $ttl = null): bool
    {
        return self::storeWrite($key, $value, $ttl !== null ? time() + $ttl : null);
    }

    private static function storeDelete(string $key): bool
    {
        $existed = self::storeRead($key) !== null;

        @unlink(self::storePath($key));

        return $existed;
    }

    private static function storeExists(string $key): bool
    {
        return self::storeRead($key) !== null;
    }

    private static function storeIncrement(string $key, int $value = 1): int
    {
        $handle = @fopen(self::storePath($key), 'c+');

        if ($handle === false) {
            return $value;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return $value;
            }

            $raw  = stream_get_contents($handle) ?: '';
            $data = json_decode($raw, true);

            $expires = is_array($data) && is_int($data['expires'] ?? null) ? $data['expires'] : null;

            if ($expires !== null && time() > $expires) {
                $data    = null;
                $expires = null;
            }

            $current = is_array($data) && is_numeric($data['value'] ?? null)
                ? (int) $data['value']
                : 0;

            $current += $value;

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode(['value' => $current, 'expires' => $expires]));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $current;
        } finally {
            fclose($handle);
        }
    }

    private static function storeExpire(string $key, int $seconds): bool
    {
        $data = self::storeRead($key);

        if ($data === null) {
            return false;
        }

        return self::storeWrite($key, $data['value'], time() + $seconds);
    }

    private static function storeTtl(string $key): int
    {
        $data = self::storeRead($key);

        if ($data === null) {
            return -2;
        }

        if ($data['expires'] === null) {
            return -1;
        }

        return max(0, $data['expires'] - time());
    }
}
