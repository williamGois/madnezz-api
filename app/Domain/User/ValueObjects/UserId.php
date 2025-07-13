<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use App\Domain\User\Exceptions\InvalidUserIdException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class UserId
{
    private UuidInterface $value;

    public function __construct(?string $value = null)
    {
        if ($value === null) {
            $this->value = Uuid::uuid4();
        } else {
            $this->validate($value);
            $this->value = Uuid::fromString($value);
        }
    }

    private function validate(string $value): void
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidUserIdException("Invalid user ID format: {$value}");
        }
    }

    public function getValue(): string
    {
        return $this->value->toString();
    }

    public function equals(UserId $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}