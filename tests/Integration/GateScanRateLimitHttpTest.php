<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Core\DB;
use PHPUnit\Framework\TestCase;

/**
 * /gate-scan is authenticated by device key and sends no cookie. Its limiter
 * used to live in $_SESSION, so every request got a fresh bucket and the limit
 * never applied. This drives the real HTTP stack (PHP's built-in server) and
 * asserts the 429 appears.
 */
final class GateScanRateLimitHttpTest extends TestCase
{
    private const TENANT    = 1;
    private const GATE_CODE = 'RLTEST';

    /** @var resource|null */
    private static $server = null;

    private static string $baseUrl = '';
    private static string $deviceKey = '';
    private static string $root = '';
    private static int $gateId = 0;
    private static string $probeCode = '';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open is unavailable, cannot start the test server.');
        }

        self::$root = dirname(__DIR__, 2);

        $db = DB::connect();

        // The HTTP server uses its own connection, so these rows must be committed.
        $db->exec("INSERT IGNORE INTO tenants (id, name, code, logo, email, phone)
            VALUES (1, 'Test Tenant', 'test', '', 'test@test.local', '+000000000')");

        $db->prepare('DELETE FROM gates WHERE tenant_id = ? AND code = ?')
            ->execute([self::TENANT, self::GATE_CODE]);

        // Committed rows survive between runs (the server has its own
        // connection); clear anything an interrupted earlier run left behind.
        $db->exec("DELETE FROM scan_logs WHERE detail LIKE 'Not found: GP-NOPE-%'");

        self::$deviceKey = 'gk_' . bin2hex(random_bytes(20));
        self::$probeCode = 'GP-NOPE-' . strtoupper(bin2hex(random_bytes(4)));

        $db->prepare('INSERT INTO gates (tenant_id, name, code, key_hash, is_active) VALUES (?, ?, ?, ?, 1)')
            ->execute([self::TENANT, 'Rate limit probe', self::GATE_CODE, hash('sha256', self::$deviceKey)]);

        self::$gateId = (int) $db->lastInsertId();

        // Start from empty buckets so an earlier run cannot pre-trip the limit.
        foreach (glob(self::$root . '/var/cache/kv/*.json') ?: [] as $file) {
            @unlink($file);
        }

        $port = self::freePort();
        self::$baseUrl = 'http://127.0.0.1:' . $port;

        $env = getenv();
        $env['DB_DATABASE']          = $_ENV['DB_DATABASE'] ?? 'glee_test';
        $env['APP_ENV']              = 'local';
        $env['APP_DEBUG']            = 'false';
        $env['REDIS_ENABLED'] = 'false';

        $log = self::$root . '/var/http-test.log';

        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', self::$root . '/public', self::$root . '/public/index.php'],
            [1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']],
            $pipes,
            self::$root,
            $env
        );

        if (!is_resource(self::$server)) {
            throw new \RuntimeException('Could not start the PHP test server.');
        }

        self::waitForServer($log);
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }

        self::$server = null;

        try {
            $db = DB::connect();

            // These rows are committed (the server has its own connection) and
            // other tests read the newest scan_logs row, so remove them by hand.
            if (self::$gateId > 0) {
                $db->prepare('DELETE FROM scan_logs WHERE gate_id = ?')->execute([self::$gateId]);
            }

            $db->prepare('DELETE FROM scan_logs WHERE detail LIKE ?')->execute(['Not found: ' . self::$probeCode . '%']);

            $db->prepare('DELETE FROM gates WHERE tenant_id = ? AND code = ?')
                ->execute([self::TENANT, self::GATE_CODE]);
        } catch (\Throwable $e) {
            // Server already verified what it needed to; cleanup is best effort.
        }

        foreach (glob(self::$root . '/var/cache/kv/*.json') ?: [] as $file) {
            @unlink($file);
        }

        @unlink(self::$root . '/var/http-test.log');

        parent::tearDownAfterClass();
    }

    public function testCookieLessScansHitTheRateLimit(): void
    {
        // The built-in server rebuilds $_SERVER for every request, so process
        // env overrides never reach the app; drive the configured ceiling.
        $config = config('security.rate_limiting.scan');
        $max    = max(1, (int) ($config['max_attempts'] ?? 5));

        $statuses = [];

        for ($i = 0; $i <= $max; $i++) {
            [$statuses[]] = self::postScan();
        }

        $this->assertNotSame(429, $statuses[0], 'the first scan must reach the controller');
        $this->assertNotSame(429, $statuses[$max - 1], 'scans up to the limit must pass');
        $this->assertSame(429, $statuses[$max], 'the scan over the limit must be refused');
    }

    /** @return array{0:int,1:string} */
    private static function postScan(): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content'       => json_encode(['key' => self::$deviceKey, 'code' => self::$probeCode]),
                'ignore_errors' => true,
                'timeout'       => 10,
            ],
        ]);

        $body = @file_get_contents(self::$baseUrl . '/gate-scan', false, $context);

        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $m)) {
                $status = (int) $m[1];
                break;
            }
        }

        return [$status, (string) $body];
    }

    private static function waitForServer(string $log): void
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 2]]);
            $body = @file_get_contents(self::$baseUrl . '/login', false, $context);

            if ($body !== false) {
                return;
            }

            usleep(100000);
        }

        $output = is_file($log) ? (string) file_get_contents($log) : '';

        throw new \RuntimeException('Test server did not become ready. Log: ' . $output);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new \RuntimeException("Could not allocate a port: {$errstr}");
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
