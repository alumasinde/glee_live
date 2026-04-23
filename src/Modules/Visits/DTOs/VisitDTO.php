<?php

declare(strict_types=1);

namespace App\Modules\Visits\DTOs;

use InvalidArgumentException;
use DateTime;
use Exception;

final class VisitDTO
{
    public readonly int $tenant_id;
    public readonly int $department_id;
    public readonly int $visitor_id;

    public readonly ?int $host_user_id;
    public readonly ?int $visit_type_id;

    public readonly string $purpose;
    public readonly ?string $expected_in;
    public readonly ?string $expected_out;

    public readonly int $created_by;

    private function __construct(
        int $tenant_id,
        int $department_id,
        int $visitor_id,
        ?int $host_user_id,
        ?int $visit_type_id,
        string $purpose,
        ?string $expected_in,
        ?string $expected_out,
        int $created_by
    ) {
        $this->tenant_id     = $tenant_id;
        $this->department_id = $department_id;
        $this->visitor_id    = $visitor_id;

        $this->host_user_id  = $host_user_id;
        $this->visit_type_id = $visit_type_id;

        $this->purpose       = self::sanitizePurpose($purpose);
        $this->expected_in   = self::normalizeDate($expected_in);
        $this->expected_out  = self::normalizeDate($expected_out);

        $this->created_by    = $created_by;

        $this->validate();
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */
    public static function fromArray(
        array $data,
        int $tenantId,
        int $userId
    ): self {

        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Invalid tenant.');
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException('Invalid user.');
        }

        return new self(
            tenant_id: $tenantId,
            department_id: self::requiredInt($data, 'department_id'),
            visitor_id: self::requiredInt($data, 'visitor_id'),
            host_user_id: self::nullablePositiveInt($data, 'host_user_id'),
            visit_type_id: self::nullablePositiveInt($data, 'visit_type_id'),
            purpose: $data['purpose'] ?? '',
            expected_in: $data['expected_in'] ?? null,
            expected_out: $data['expected_out'] ?? null,
            created_by: $userId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    private function validate(): void
    {
        if ($this->department_id <= 0) {
            throw new InvalidArgumentException('Department is required.');
        }

        if ($this->visitor_id <= 0) {
            throw new InvalidArgumentException('Visitor is required.');
        }

        if ($this->host_user_id !== null && $this->host_user_id <= 0) {
            throw new InvalidArgumentException('Invalid host selected.');
        }

        if ($this->expected_in && $this->expected_out) {
            if (strtotime($this->expected_out) < strtotime($this->expected_in)) {
                throw new InvalidArgumentException(
                    'Expected checkout cannot be before expected checkin.'
                );
            }
        }

        if (strlen($this->purpose) > 1000) {
            throw new InvalidArgumentException('Purpose is too long.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Normalizers
    |--------------------------------------------------------------------------
    */
    private static function sanitizePurpose(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Purpose is required.');
        }

        return $value;
    }

    private static function normalizeDate(?string $date): ?string
    {
        if (!$date || trim($date) === '') {
            return null;
        }

        try {
            $dt = new DateTime($date);
            return $dt->format('Y-m-d H:i:s');
        } catch (Exception) {
            throw new InvalidArgumentException('Invalid date format.');
        }
    }

    private static function requiredInt(array $data, string $key): int
    {
        if (!isset($data[$key]) || (int)$data[$key] <= 0) {
            throw new InvalidArgumentException(
                ucfirst(str_replace('_', ' ', $key)) . ' is required.'
            );
        }

        return (int) $data[$key];
    }

    private static function nullablePositiveInt(array $data, string $key): ?int
    {
        if (!isset($data[$key]) || $data[$key] === '') {
            return null;
        }

        $value = (int) $data[$key];

        return $value > 0 ? $value : null;
    }
}