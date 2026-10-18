<?php

namespace App\Core;

use SessionHandlerInterface;

class RedisSessionHandler implements SessionHandlerInterface
{
    private ?\Predis\Client $redis;
    private int $ttl;
    private string $prefix;

    public function __construct(int $ttl = 7200, string $prefix = 'session:')
    {
        $this->ttl = $ttl;
        $this->prefix = $prefix;
        $this->redis = Redis::connect();
    }

    public function open($savePath, $sessionName): bool
    {
        return $this->redis !== null;
    }

    public function close(): bool
    {
        return true;
    }

    public function read($sessionId): string
    {
        if ($this->redis === null) {
            return '';
        }

        $key = $this->prefix . $sessionId;
        $data = $this->redis->get($key);
        return $data !== null ? $data : '';
    }

    public function write($sessionId, $sessionData): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $key = $this->prefix . $sessionId;
        return $this->redis->setex($key, $this->ttl, $sessionData) === 'OK';
    }

    public function destroy($sessionId): bool
    {
        if ($this->redis === null) {
            return false;
        }

        $key = $this->prefix . $sessionId;
        return $this->redis->del($key) > 0;
    }

    public function gc($maxlifetime): bool
    {
        // Redis handles expiration automatically via TTL
        return true;
    }
}
