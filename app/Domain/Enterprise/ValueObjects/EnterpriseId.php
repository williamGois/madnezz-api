<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ValueObjects;

use Ramsey\Uuid\Uuid;

class EnterpriseId
{
    private string $value;

    public function __construct(string $value)
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException('Invalid enterprise ID format');
        }
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(EnterpriseId $other): bool
    {
        return $this->value === $other->value;
    }
}