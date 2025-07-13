<?php

declare(strict_types=1);

namespace App\Application\Contracts;

use App\Domain\User\Entities\User;

interface TokenServiceInterface
{
    public function generateToken(User $user): string;
    
    public function refreshToken(): string;
    
    public function invalidateToken(): void;
    
    public function getTokenTtl(): int;
    
    public function getCurrentUser(): ?User;
}