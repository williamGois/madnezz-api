<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use App\Domain\Shared\Exceptions\InvalidEmailException;

final class Email
{
    private string $value;

    public function __construct(string $email)
    {
        $this->validate($email);
        $this->value = $email;
    }

    private function validate(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email format: {$email}");
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}