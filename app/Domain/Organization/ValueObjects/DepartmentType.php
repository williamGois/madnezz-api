<?php

declare(strict_types=1);

namespace App\Domain\Organization\ValueObjects;

use App\Domain\Organization\Exceptions\InvalidDepartmentTypeException;

final class DepartmentType
{
    public const ADMINISTRATIVE = 'administrative';
    public const FINANCIAL = 'financial';
    public const MARKETING = 'marketing';
    public const OPERATIONS = 'operations';
    public const TRADE = 'trade';
    public const MACRO = 'macro';

    private const VALID_TYPES = [
        self::ADMINISTRATIVE,
        self::FINANCIAL,
        self::MARKETING,
        self::OPERATIONS,
        self::TRADE,
        self::MACRO,
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
            throw new InvalidDepartmentTypeException("Invalid department type: {$type}");
        }
    }

    public static function administrative(): self
    {
        return new self(self::ADMINISTRATIVE);
    }

    public static function financial(): self
    {
        return new self(self::FINANCIAL);
    }

    public static function marketing(): self
    {
        return new self(self::MARKETING);
    }

    public static function operations(): self
    {
        return new self(self::OPERATIONS);
    }

    public static function trade(): self
    {
        return new self(self::TRADE);
    }

    public static function macro(): self
    {
        return new self(self::MACRO);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDisplayName(): string
    {
        return match ($this->value) {
            self::ADMINISTRATIVE => 'Administrativo',
            self::FINANCIAL => 'Financeiro',
            self::MARKETING => 'Marketing',
            self::OPERATIONS => 'Operações',
            self::TRADE => 'Trade',
            self::MACRO => 'Macro',
        };
    }

    public function equals(DepartmentType $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}