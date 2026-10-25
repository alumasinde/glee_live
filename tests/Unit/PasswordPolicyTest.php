<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Auth\Services\AuthService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Password policy comes from config/security.php.
 *
 * It used to be hardcoded to 12 characters while installs could set
 * PASSWORD_MIN_LENGTH=7, so the .env value was silently ignored. These tests
 * assert the checks follow whatever the config resolves to, not a fixed number.
 */
final class PasswordPolicyTest extends TestCase
{
    private int $minLength;

    /** @var array<string, bool> */
    private array $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $policy = (array) config('security.password');

        // Same floor AuthService applies: a mistyped min_length must not
        // silently weaken every account created from then on.
        $this->minLength = max(8, (int) ($policy['min_length'] ?? 12));

        $this->rules = [
            'uppercase'       => (bool) ($policy['require_uppercase'] ?? true),
            'lowercase'       => (bool) ($policy['require_lowercase'] ?? true),
            'numbers'         => (bool) ($policy['require_numbers'] ?? true),
            'require_special' => (bool) ($policy['require_special_chars'] ?? false),
        ];
    }

    public function testTooShortIsRejectedRegardlessOfComposition(): void
    {
        $password = substr(str_repeat('A1a', intdiv($this->minLength, 3) + 2), 0, $this->minLength - 1);

        $this->assertSame($this->minLength - 1, strlen($password));
        $this->assertRejected($password);
    }

    public function testPasswordMeetingEveryEnabledRulePasses(): void
    {
        $password = 'Aa1' . str_repeat('b', $this->minLength - 3);

        if ($this->rules['require_special']) {
            $password .= '!';
        }

        AuthService::assertStrongPassword($password);

        $this->assertGreaterThanOrEqual($this->minLength, strlen($password));
    }

    public function testEachCharacterClassIsEnforcedOnlyWhenConfigured(): void
    {
        $without = [
            'uppercase' => str_repeat('a', $this->minLength - 2) . '1a',
            'lowercase' => str_repeat('A', $this->minLength - 2) . '1A',
            'numbers'   => 'A' . str_repeat('a', $this->minLength - 1),
        ];

        foreach ($without as $rule => $password) {
            if ($this->rules['require_special']) {
                $password .= '!';
            }

            $this->assertSame($this->minLength + ($this->rules['require_special'] ? 1 : 0), strlen($password));

            if ($this->rules[$rule]) {
                $this->assertRejected($password, "missing {$rule}");
            } else {
                AuthService::assertStrongPassword($password);
            }
        }
    }

    public function testSpecialCharactersFollowTheSetting(): void
    {
        $plain = 'Aa1' . str_repeat('b', $this->minLength - 3);
        $withSpecial = $plain . '!';

        if ($this->rules['require_special']) {
            AuthService::assertStrongPassword($withSpecial);
            $this->assertRejected($plain, 'missing special');
        } else {
            AuthService::assertStrongPassword($plain);
        }

        $this->assertTrue(true);
    }

    public function testEmptyPasswordIsRejected(): void
    {
        $this->assertRejected('');
    }

    private function assertRejected(string $password, string $context = ''): void
    {
        try {
            AuthService::assertStrongPassword($password);
        } catch (RuntimeException $e) {
            $this->assertNotSame('', $e->getMessage());

            return;
        }

        $this->fail('Expected rejection' . ($context === '' ? '' : " ({$context})") . '.');
    }
}
