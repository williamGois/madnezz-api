<?php

declare(strict_types=1);

namespace App\Domain\Organization\ValueObjects;

use App\Domain\Organization\Exceptions\InvalidOrganizationIdException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class OrganizationId
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
            throw new InvalidOrganizationIdException("Invalid organization ID format: {$value}");
        }
    }

    public function getValue(): string
    {
        return $this->value->toString();
    }

    public function equals(OrganizationId $other): bool
    {
        return $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return $this->getValue();
    }
}