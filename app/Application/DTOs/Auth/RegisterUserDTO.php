<?php

declare(strict_types=1);

namespace App\Application\DTOs\Auth;

final class RegisterUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password
    ) {
    }
}