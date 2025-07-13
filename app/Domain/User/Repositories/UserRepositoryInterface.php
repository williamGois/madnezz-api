<?php

declare(strict_types=1);

namespace App\Domain\User\Repositories;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;

interface UserRepositoryInterface
{
    public function findById(UserId $id): ?User;
    
    public function findByEmail(Email $email): ?User;
    
    public function save(User $user): void;
    
    public function delete(User $user): void;
    
    public function existsByEmail(Email $email): bool;
    
    public function existsByEmailExcludingId(Email $email, UserId $excludeId): bool;
    
    public function findAll(): array;
    
    public function findActiveUsers(): array;
    
    public function countAll(): int;
}