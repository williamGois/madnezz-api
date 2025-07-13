<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ValueObjects;

class EnterpriseStatus
{
    private const ACTIVE = 'ACTIVE';
    private const INACTIVE = 'INACTIVE';
    private const UNDER_CONSTRUCTION = 'UNDER_CONSTRUCTION';
    private const SUSPENDED = 'SUSPENDED';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::INACTIVE,
        self::UNDER_CONSTRUCTION,
        self::SUSPENDED,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper($value);
        
        if (!in_array($value, self::VALID_STATUSES)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid enterprise status. Valid values are: %s',
                implode(', ', self::VALID_STATUSES)
            ));
        }

        $this->value = $value;
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function underConstruction(): self
    {
        return new self(self::UNDER_CONSTRUCTION);
    }

    public static function suspended(): self
    {
        return new self(self::SUSPENDED);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->value === self::INACTIVE;
    }

    public function isUnderConstruction(): bool
    {
        return $this->value === self::UNDER_CONSTRUCTION;
    }

    public function isSuspended(): bool
    {
        return $this->value === self::SUSPENDED;
    }

    public function canHaveStores(): bool
    {
        return in_array($this->value, [self::ACTIVE, self::UNDER_CONSTRUCTION]);
    }

    public function equals(EnterpriseStatus $other): bool
    {
        return $this->value === $other->value;
    }
}