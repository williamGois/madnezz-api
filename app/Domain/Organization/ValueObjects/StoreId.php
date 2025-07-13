<?php

declare(strict_types=1);

namespace App\Domain\Organization\ValueObjects;

use App\Domain\Organization\Exceptions\InvalidStoreIdException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class StoreId
{
    private UuidInterface $value;

    public function __construct(?string $value = null)
    {
        if ($value === null) {
            $this->value = Uuid::uuid4();
        } else {
            $this->validateUuid($value);
            $this->value = Uuid::fromString($value);
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value->toString();
    }

    public function equals(StoreId $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private function validateUuid(string $value): void
    {
        if (!Uuid::isValid($value)) {
            throw new InvalidStoreIdException("Invalid store ID: {$value}");
        }
    }
}