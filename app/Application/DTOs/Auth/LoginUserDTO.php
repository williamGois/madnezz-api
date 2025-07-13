<?php

declare(strict_types=1);

namespace App\Application\DTOs\Auth;

final class LoginUserDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password
    ) {
    }
}