<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use App\Domain\User\Exceptions\InvalidUserStatusException;

final class UserStatus
{
    private const ACTIVE = 'active';
    private const INACTIVE = 'inactive';
    private const SUSPENDED = 'suspended';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::INACTIVE,
        self::SUSPENDED,
    ];

    private string $value;

    public function __construct(string $status)
    {
        $this->validate($status);
        $this->value = $status;
    }

    private function validate(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidUserStatusException("Invalid user status: {$status}");
        }
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function inactive(): self
    {
        return new self(self::INACTIVE);
    }

    public static function suspended(): self
    {
        return new self(self::SUSPENDED);
    }

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->value === self::INACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->value === self::SUSPENDED;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}