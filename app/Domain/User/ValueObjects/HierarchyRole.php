<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use App\Domain\User\Exceptions\InvalidHierarchyRoleException;

class HierarchyRole
{
    public const MASTER = 'MASTER';
    public const GO = 'GO';
    public const GR = 'GR';
    public const STORE_MANAGER = 'STORE_MANAGER';

    private const VALID_ROLES = [
        self::MASTER,
        self::GO,
        self::GR,
        self::STORE_MANAGER
    ];

    private const HIERARCHY_LEVELS = [
        self::MASTER => 1,
        self::GO => 2,
        self::GR => 3,
        self::STORE_MANAGER => 4
    ];

    private string $value;

    public function __construct(string $value)
    {
        if (!in_array($value, self::VALID_ROLES, true)) {
            throw new InvalidHierarchyRoleException("Invalid hierarchy role: {$value}");
        }
        $this->value = $value;
    }

    public static function master(): self
    {
        return new self(self::MASTER);
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

    public function getLevel(): int
    {
        return self::HIERARCHY_LEVELS[$this->value];
    }

    public function isMaster(): bool
    {
        return $this->value === self::MASTER;
    }

    public function isGo(): bool
    {
        return $this->value === self::GO;
    }

    public function isGr(): bool
    {
        return $this->value === self::GR;
    }

    public function isStoreManager(): bool
    {
        return $this->value === self::STORE_MANAGER;
    }

    public function canAccessLevel(HierarchyRole $targetRole): bool
    {
        return $this->getLevel() <= $targetRole->getLevel();
    }

    public function canManageLevel(HierarchyRole $targetRole): bool
    {
        return $this->getLevel() < $targetRole->getLevel();
    }

    public function equals(HierarchyRole $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function getValidRoles(): array
    {
        return self::VALID_ROLES;
    }

    public static function getRoleHierarchy(): array
    {
        return self::HIERARCHY_LEVELS;
    }
}