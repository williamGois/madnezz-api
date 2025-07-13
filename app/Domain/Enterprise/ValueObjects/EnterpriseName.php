<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\ValueObjects;

class EnterpriseName
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        
        if (empty($value)) {
            throw new \InvalidArgumentException('Enterprise name cannot be empty');
        }

        if (strlen($value) < 3) {
            throw new \InvalidArgumentException('Enterprise name must be at least 3 characters long');
        }

        if (strlen($value) > 255) {
            throw new \InvalidArgumentException('Enterprise name cannot exceed 255 characters');
        }

        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(EnterpriseName $other): bool
    {
        return $this->value === $other->value;
    }
}