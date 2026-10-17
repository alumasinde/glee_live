<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Gatepass\DTOs\GatepassDTO;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GatepassDTOTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return GatepassDTO
     */
    private static function dto(array $overrides = []): GatepassDTO
    {
        return new GatepassDTO(...array_merge([
            'tenant_id' => 1,
            'visit_id' => null,
            'gatepass_type_id' => null,
            'purpose' => 'Laptop service',
            'is_returnable' => false,
            'expected_return_date' => null,
            'needs_approval' => true,
            'created_by' => 7,
            'items' => [],
        ], $overrides));
    }

    public function testValidDtoNormalisesValues(): void
    {
        $dto = self::dto(["purpose" => "  Trim me  \n"]);

        $this->assertSame('Trim me', $dto->purpose);
        $this->assertTrue($dto->needs_approval);
        $this->assertFalse($dto->is_returnable);
        $this->assertNull($dto->expected_return_date);
    }

    #[DataProvider('blankPurposeProvider')]
    public function testBlankPurposeIsRejected(string $purpose): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::dto(["purpose" => $purpose]);
    }

    public static function blankPurposeProvider(): array
    {
        return [[''], ['   '], ["\t\n"]];
    }

    public function testInvalidTenantIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tenant.');
        self::dto(["tenant_id" => 0]);
    }

    public function testInvalidCreatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid creator.');
        self::dto(["created_by" => -1]);
    }

    #[DataProvider('truthyProvider')]
    public function testFlagsAreCoercedFromRequestStyleValues(bool|string|int $raw, bool $expected): void
    {
        $dto = self::dto(["is_returnable" => $raw, "expected_return_date" => '2026-10-20']);

        $this->assertSame($expected, $dto->is_returnable);
    }

    public static function truthyProvider(): array
    {
        return [
            ['1', true],
            ['true', true],
            [1, true],
            ['0', false],
            ['false', false],
            ['', false],
            [0, false],
        ];
    }

    public function testReturnableRequiresExpectedReturnDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected return date is required');
        self::dto(["is_returnable" => true]);
    }

    public function testExpectedReturnDateIsNormalised(): void
    {
        $dto = self::dto(["is_returnable" => true, "expected_return_date" => '2026-11-05 14:30:00']);

        $this->assertSame('2026-11-05', $dto->expected_return_date);
    }

    public function testGarbageDateIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        self::dto(["is_returnable" => true, "expected_return_date" => 'not-a-date']);
    }

    public function testNonPositiveIdsBecomeNull(): void
    {
        $dto = self::dto(["visit_id" => 0, "gatepass_type_id" => -3]);

        $this->assertNull($dto->visit_id);
        $this->assertNull($dto->gatepass_type_id);
    }

    public function testItemsAreSanitised(): void
    {
        $dto = self::dto(["items" => [
            ['item_name' => '  Laptop  ', 'quantity' => 0, 'description' => '  ', 'serial_number' => ' SN1 '],
            ['item_name' => '   '],
            ['description' => 'nameless row'],
        ]]);

        $this->assertCount(1, $dto->items);
        $this->assertSame('Laptop', $dto->items[0]['item_name']);
        $this->assertSame(1, $dto->items[0]['quantity']);
        $this->assertNull($dto->items[0]['description']);
        $this->assertSame('SN1', $dto->items[0]['serial_number']);
    }

    public function testToArrayUsesPersistenceKeys(): void
    {
        $array = self::dto()->toArray();

        $this->assertArrayHasKey('gatepass_type_id', $array);
        $this->assertArrayNotHasKey('items', $array);
        $this->assertSame(1, $array['tenant_id']);
    }
}
