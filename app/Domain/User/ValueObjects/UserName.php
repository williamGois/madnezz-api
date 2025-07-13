<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use App\Domain\User\Exceptions\InvalidUserNameException;

final class UserName
{
    private string $value;

    public function __construct(string $name)
    {
        $this->validate($name);
        $this->value = trim($name);
    }

    private function validate(string $name): void
    {
        $name = trim($name);
        
        if (empty($name)) {
            throw new InvalidUserNameException("User name cannot be empty");
        }

        if (strlen($name) < 2) {
            throw new InvalidUserNameException("User name must be at least 2 characters long");
        }

        if (strlen($name) > 255) {
            throw new InvalidUserNameException("User name cannot exceed 255 characters");
        }
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