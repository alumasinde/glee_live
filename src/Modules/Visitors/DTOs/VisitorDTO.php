<?php

declare(strict_types=1);

namespace App\Modules\Visitors\DTOs;

use InvalidArgumentException;

final class VisitorDTO
{
    public readonly int $tenant_id;
    public readonly string $first_name;
    public readonly string $last_name;
    public readonly ?int $id_type_id;
    public readonly ?string $id_number;
    public readonly ?string $phone;
    public readonly ?string $email;
    public readonly ?int $company_id;
    
public readonly ?int $created_by;    
public readonly ?string $new_company_name;

    private function __construct(
        int $tenant_id,
        string $first_name,
        string $last_name,
        ?int $id_type_id,
        ?string $id_number,
        ?string $phone,
        ?string $email,
        ?int $company_id,
        ?int $created_by,        
        ?string $new_company_name
    ) {
        $this->tenant_id = $tenant_id;
        $this->first_name = self::normalizeName($first_name);
        $this->last_name = self::normalizeName($last_name);
        $this->id_type_id = $id_type_id;
        $this->id_number = self::nullableTrim($id_number);
        $this->phone = self::nullableTrim($phone);
        $this->email = self::normalizeEmail($email);
        $this->company_id = $company_id;
        $this->created_by = $created_by;
        $this->new_company_name = self::nullableTrim($new_company_name);

        $this->validate();
    }

    /*
    |--------------------------------------------------------------------------
    | Factory
    |--------------------------------------------------------------------------
    */

    public static function fromArray(array $data, int $tenant_id): self
    {
        return new self(
            tenant_id: $tenant_id,
            first_name: $data['first_name'] ?? '',
            last_name: $data['last_name'] ?? '',
            id_type_id: isset($data['id_type_id']) ? (int) $data['id_type_id'] : null,
            id_number: $data['id_number'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            company_id: isset($data['company_id']) ? (int) $data['company_id'] : null,
            created_by: isset($data['created_by']) ? (int) $data['created_by'] : null,
            new_company_name: $data['new_company_name'] ?? null
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalizers
    |--------------------------------------------------------------------------
    */

    private static function normalizeName(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Name fields cannot be empty.');
        }

        return ucwords(strtolower($value));
    }

    private static function normalizeEmail(?string $email): ?string
    {
        if (!$email) {
            return null;
        }

        $email = trim(strtolower($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        return $email;
    }

    private static function nullableTrim(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    private function validate(): void
    {
        if ($this->company_id && $this->new_company_name) {
            throw new InvalidArgumentException(
                'Provide either company_id OR new_company_name, not both.'
            );
        }

        if ($this->id_number && !$this->id_type_id) {
            throw new InvalidArgumentException(
                'ID type must be provided when ID number is set.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function hasNewCompany(): bool
    {
        return $this->new_company_name !== null;
    }

    public function hasCompany(): bool
    {
        return $this->company_id !== null;
    }
}