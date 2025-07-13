<?php

declare(strict_types=1);

namespace App\Application\DTOs\Auth;

use App\Domain\User\Entities\User;

final class AuthResultDTO
{
    public function __construct(
        public readonly User $user,
        public readonly string $token,
        public readonly string $tokenType,
        public readonly int $expiresIn
    ) {
    }
}