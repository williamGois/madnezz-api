<?php

declare(strict_types=1);

namespace App\Domain\Organization\ValueObjects;

use App\Domain\Organization\Exceptions\InvalidPositionLevelException;

final class PositionLevel
{
    public const GO = 'go';
    public const GR = 'gr';
    public const STORE_MANAGER = 'store_manager';

    private const VALID_LEVELS = [
        self::GO,
        self::GR,
        self::STORE_MANAGER,
    ];

    private const HIERARCHY_ORDER = [
        self::GO => 1,
        self::GR => 2,
        self::STORE_MANAGER => 3,
    ];

    private string $value;

    public function __construct(string $level)
    {
        $this->validate($level);
        $this->value = $level;
    }

    private function validate(string $level): void
    {
        if (!in_array($level, self::VALID_LEVELS, true)) {
            throw new InvalidPositionLevelException("Invalid position level: {$level}");
        }
    }

    public static function go(): self
    {
        return new self(self::GO);
    }

    public static function gr(): self
    {
        return new self(self::GR);
    }

    public static function storeManager(): self
    {
        return new self(self::STORE_MANAGER);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getHierarchyOrder(): int
    {
        return self::HIERARCHY_ORDER[$this->value];
    }

    public function isHigherThan(PositionLevel $other): bool
    {
        return $this->getHierarchyOrder() < $other->getHierarchyOrder();
    }

    public function isLowerThan(PositionLevel $other): bool
    {
        return $this->getHierarchyOrder() > $other->getHierarchyOrder();
    }

    public function isSameLevel(PositionLevel $other): bool
    {
        return $this->getHierarchyOrder() === $other->getHierarchyOrder();
    }

    public function canManage(PositionLevel $other): bool
    {
        return $this->isHigherThan($other) || $this->isSameLevel($other);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}