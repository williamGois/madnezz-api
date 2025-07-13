<?php

declare(strict_types=1);

namespace App\Domain\Organization\ValueObjects;

use App\Domain\Organization\Exceptions\InvalidOrganizationUnitTypeException;

final class OrganizationUnitType
{
    public const COMPANY = 'company';
    public const REGIONAL = 'regional';
    public const STORE = 'store';

    private const VALID_TYPES = [
        self::COMPANY,
        self::REGIONAL,
        self::STORE,
    ];

    private string $value;

    public function __construct(string $type)
    {
        $this->validate($type);
        $this->value = $type;
    }

    private function validate(string $type): void
    {
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new InvalidOrganizationUnitTypeException("Invalid organization unit type: {$type}");
        }
    }

    public static function company(): self
    {
        return new self(self::COMPANY);
    }

    public static function regional(): self
    {
        return new self(self::REGIONAL);
    }

    public static function store(): self
    {
        return new self(self::STORE);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isCompany(): bool
    {
        return $this->value === self::COMPANY;
    }

    public function isRegional(): bool
    {
        return $this->value === self::REGIONAL;
    }

    public function isStore(): bool
    {
        return $this->value === self::STORE;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}