<?php

declare(strict_types=1);

namespace App\Application\DTOs\User;

final class ChangePasswordDTO
{
    public function __construct(
        public readonly string $currentPassword,
        public readonly string $newPassword
    ) {
    }
}