<?php

namespace App\Modules\Gatepass\DTOs;

use DateTime;
use InvalidArgumentException;

class GatepassDTO
{
    public int $tenant_id;
    public ?int $visit_id;
    public ?int $gatepass_type_id;
    public string $purpose;
    public bool $is_returnable;
    public ?int $checked_in_by;
    public ?int $checked_out_by;
    public ?string $expected_return_date;
    public bool $needs_approval;
    public int $created_by;

    /** @var array<int, array<string, mixed>> */
    public array $items;

    public function __construct(
        int $tenant_id,
        ?int $visit_id,
        ?int $gatepass_type_id,
        string $purpose,
        bool|int|string $is_returnable,
        ?string $expected_return_date,
        bool|int|string $needs_approval,
        int $created_by,
        array $items = [],
        ?int $checked_in_by = null,
        ?int $checked_out_by = null,
    ) {
        $purpose = trim($purpose);

        if ($purpose === '') {
            throw new InvalidArgumentException('Purpose is required.');
        }

        if ($tenant_id <= 0) {
            throw new InvalidArgumentException('Invalid tenant.');
        }

        if ($created_by <= 0) {
            throw new InvalidArgumentException('Invalid creator.');
        }

        $this->tenant_id        = $tenant_id;
        $this->visit_id         = $this->nullablePositiveInt($visit_id);
        $this->gatepass_type_id = $this->nullablePositiveInt($gatepass_type_id);
        $this->purpose          = $purpose;
        $this->is_returnable    = $this->toBool($is_returnable);
        $this->needs_approval   = $this->toBool($needs_approval);
        $this->created_by       = $created_by;
        $this->checked_in_by    = $this->nullablePositiveInt($checked_in_by);
        $this->checked_out_by   = $this->nullablePositiveInt($checked_out_by);
        $this->expected_return_date = $this->normalizeDate($expected_return_date);

        if ($this->is_returnable && !$this->expected_return_date) {
            throw new InvalidArgumentException(
                'Expected return date is required for returnable gatepasses.'
            );
        }

        $this->items = $this->sanitizeItems($items);
    }

    public function toArray(): array
    {
        return [
            'tenant_id'            => $this->tenant_id,
            'visit_id'             => $this->visit_id,
            'gatepass_type_id'     => $this->gatepass_type_id,
            'purpose'              => $this->purpose,
            'is_returnable'        => $this->is_returnable,
            'expected_return_date' => $this->expected_return_date,
            'needs_approval'       => $this->needs_approval,
            'created_by'           => $this->created_by,
            'checked_in_by'        => $this->checked_in_by,
            'checked_out_by'       => $this->checked_out_by,
        ];
    }

    private function toBool(bool|int|string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function nullablePositiveInt(?int $value): ?int
    {
        return ($value && $value > 0) ? $value : null;
    }

    private function normalizeDate(?string $date): ?string
    {
        if (!$date) {
            return null;
        }

        try {
            return (new DateTime($date))->format('Y-m-d');
        } catch (\Exception) {
            throw new InvalidArgumentException('Invalid date format.');
        }
    }

    private function sanitizeItems(array $items): array
    {
        $clean = [];

        foreach ($items as $item) {
            $name = trim((string) ($item['item_name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $clean[] = [
                'item_name'     => $name,
                'description'   => trim((string) ($item['description'] ?? '')) ?: null,
                'quantity'      => max(1, (int) ($item['quantity'] ?? 1)),
                'serial_number' => trim((string) ($item['serial_number'] ?? '')) ?: null,
                'is_returnable' => $this->toBool($item['is_returnable'] ?? false),
            ];
        }

        return $clean;
    }
}
