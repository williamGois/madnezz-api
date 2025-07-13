<?php

declare(strict_types=1);

namespace App\Application\DTOs\User;

final class UpdateUserProfileDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email
    ) {
    }
}