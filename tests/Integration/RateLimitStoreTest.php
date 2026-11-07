<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Redis;
use PHPUnit\Framework\TestCase;

/**
 * The fallback store used when Redis is not configured was $_SESSION, so the
 * rate limiter was per-browser: dropping the cookie reset the counter, and the
 * cookie-less gate device endpoint got a fresh bucket on every request.
 */
final class RateLimitStoreTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->key = 'rate_limit:test:' . bin2hex(random_bytes(6));
        Redis::delete($this->key);
    }

    protected function tearDown(): void
    {
        Redis::delete($this->key);
        $_SESSION = [];
    }

    public function testCountersAreSharedAcrossRequestsWithoutSessionState(): void
    {
        $this->assertSame(1, Redis::increment($this->key));
        $this->assertSame(2, Redis::increment($this->key));

        // A later request arrives with no session data at all.
        $_SESSION = [];

        $this->assertSame(2, Redis::get($this->key));
        $this->assertTrue(Redis::exists($this->key));
        $this->assertArrayNotHasKey('redis_fallback:' . $this->key, $_SESSION);
    }

    public function testExpireAndDeleteBehaveLikeRedis(): void
    {
        Redis::set($this->key, ['attempts' => 1], 300);

        $this->assertTrue(Redis::expire($this->key, 60));
        $this->assertGreaterThan(0, Redis::ttl($this->key));
        $this->assertLessThanOrEqual(60, Redis::ttl($this->key));

        $this->assertTrue(Redis::delete($this->key));
        $this->assertFalse(Redis::exists($this->key));
        $this->assertSame(-2, Redis::ttl($this->key));
    }

    public function testMissingKeyReturnsTheDefault(): void
    {
        $this->assertNull(Redis::get($this->key));
        $this->assertSame('fallback', Redis::get($this->key, 'fallback'));
        $this->assertSame(-2, Redis::ttl($this->key));
    }
}
