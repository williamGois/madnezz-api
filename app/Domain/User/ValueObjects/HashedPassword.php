<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObjects;

use App\Domain\User\Exceptions\InvalidPasswordException;

final class HashedPassword
{
    private string $value;

    public function __construct(string $hashedPassword)
    {
        $this->validate($hashedPassword);
        $this->value = $hashedPassword;
    }

    private function validate(string $hashedPassword): void
    {
        if (empty($hashedPassword)) {
            throw new InvalidPasswordException("Hashed password cannot be empty");
        }

        // Basic validation for bcrypt hash
        if (!password_get_info($hashedPassword)['algo']) {
            throw new InvalidPasswordException("Invalid password hash format");
        }
    }

    public static function fromPlainText(string $plainPassword): self
    {
        if (strlen($plainPassword) < 8) {
            throw new InvalidPasswordException("Password must be at least 8 characters long");
        }

        return new self(password_hash($plainPassword, PASSWORD_DEFAULT));
    }

    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->value);
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