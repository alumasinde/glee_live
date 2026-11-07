<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Redis;

class RateLimitMiddleware
{
    private string $key;
    private int $maxAttempts;
    private int $decayMinutes;

    public function __construct(
        string $key = 'default',
        int $maxAttempts = 5,
        int $decayMinutes = 15
    ) {
        $this->key = $key;
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public function handle(?Request $request = null, ...$args)
    {
        // Allow dynamic override via middleware arguments
        if (count($args) >= 3) {
            $this->key = $args[0];
            $this->maxAttempts = (int) $args[1];
            $this->decayMinutes = (int) $args[2];
        } else {
            // Routes declare their bucket as "RateLimitMiddleware:scan". Ignoring
            // that argument left every limiter sharing the "default" bucket (and
            // its 5/15min ceiling), so the configured per-route limits — and the
            // matching RateLimitMiddleware::reset() calls — never applied.
            if (!empty($args[0])) {
                $this->key = (string) $args[0];
            }

            // Use config values if not overridden
            $config = function_exists('config') ? config("security.rate_limiting.{$this->key}") : null;
            if ($config) {
                $this->maxAttempts = $config['max_attempts'] ?? $this->maxAttempts;
                $this->decayMinutes = $config['decay_minutes'] ?? $this->decayMinutes;
            }
        }

        // Check if rate limiting is globally enabled
        if (function_exists('env') && !env('RATE_LIMIT_ENABLED', true)) {
            return;
        }

        $identifier = $this->resolveIdentifier($request);
        $attempts = $this->getAttempts($identifier);

        if ($attempts >= $this->maxAttempts) {
            $retryAfter = $this->availableIn($identifier);
            Response::abort(429, "Too many attempts. Please try again in {$retryAfter} seconds.");
        }

        $this->incrementAttempts($identifier);
    }

    private function resolveIdentifier(?Request $request): string
    {
        return self::identifier($this->key, $request);
    }

    /**
     * The bucket a request counts against: IP plus, for credential forms, the
     * submitted email so one address cannot be sprayed across many accounts.
     */
    public static function identifier(string $key, ?Request $request = null): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($request && $request->input('email')) {
            return $key . ':' . $ip . ':' . md5((string) $request->input('email'));
        }

        return $key . ':' . $ip;
    }

    /**
     * Clear the bucket for a successful authentication, so a legitimate user who
     * signs in repeatedly is not locked out by their own successes.
     */
    public static function reset(string $key, ?Request $request = null): void
    {
        Redis::delete('rate_limit:' . self::identifier($key, $request));
    }

    private function getAttempts(string $identifier): int
    {
        $key = 'rate_limit:' . $identifier;
        $data = Redis::get($key);

        if (!$data || $this->isExpired($data)) {
            return 0;
        }

        return (int) $data['attempts'];
    }

    private function incrementAttempts(string $identifier): void
    {
        $key = 'rate_limit:' . $identifier;
        $data = Redis::get($key);

        if (!$data || $this->isExpired($data)) {
            $data = [
                'attempts' => 1,
                'timestamp' => time(),
            ];
        } else {
            $data['attempts']++;
        }

        $ttl = $this->decayMinutes * 60;
        Redis::set($key, $data, $ttl);
    }

    private function isExpired(array $data): bool
    {
        return time() - $data['timestamp'] > ($this->decayMinutes * 60);
    }

    private function availableIn(string $identifier): int
    {
        $key = 'rate_limit:' . $identifier;
        $data = Redis::get($key);

        if (!$data) {
            return 0;
        }

        $expiresAt = $data['timestamp'] + ($this->decayMinutes * 60);
        $remaining = $expiresAt - time();

        return max(0, $remaining);
    }

    /**
     * Clear rate limit for a specific identifier (useful for testing)
     */
    public function clear(string $identifier): void
    {
        $key = 'rate_limit:' . $identifier;
        Redis::delete($key);
    }
}
