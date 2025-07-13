<?php

declare(strict_types=1);

namespace App\Domain\Task\ValueObjects;

class TaskPriority
{
    public const LOW = 'LOW';
    public const MEDIUM = 'MEDIUM';
    public const HIGH = 'HIGH';
    public const URGENT = 'URGENT';
    
    private const VALID_PRIORITIES = [
        self::LOW,
        self::MEDIUM,
        self::HIGH,
        self::URGENT
    ];
    
    private const PRIORITY_WEIGHTS = [
        self::LOW => 1,
        self::MEDIUM => 2,
        self::HIGH => 3,
        self::URGENT => 4
    ];
    
    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_PRIORITIES)) {
            throw new \InvalidArgumentException("Invalid task priority: {$value}");
        }
        
        $this->value = $value;
    }

    public static function low(): self
    {
        return new self(self::LOW);
    }

    public static function medium(): self
    {
        return new self(self::MEDIUM);
    }

    public static function high(): self
    {
        return new self(self::HIGH);
    }

    public static function urgent(): self
    {
        return new self(self::URGENT);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getWeight(): int
    {
        return self::PRIORITY_WEIGHTS[$this->value];
    }

    public function isHigherThan(TaskPriority $other): bool
    {
        return $this->getWeight() > $other->getWeight();
    }

    public function isLowerThan(TaskPriority $other): bool
    {
        return $this->getWeight() < $other->getWeight();
    }

    public function equals(TaskPriority $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}