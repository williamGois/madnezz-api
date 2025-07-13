<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entities\User;
use App\Domain\User\Exceptions\UserAlreadyExistsException;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\ValueObjects\UserId;

class UserDomainService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function ensureUserDoesNotExist(Email $email): void
    {
        if ($this->userRepository->existsByEmail($email)) {
            throw new UserAlreadyExistsException("User with email {$email} already exists");
        }
    }

    public function ensureUserExists(UserId $id): User
    {
        $user = $this->userRepository->findById($id);
        
        if (!$user) {
            throw new UserNotFoundException("User with ID {$id} not found");
        }
        
        return $user;
    }

    public function ensureEmailIsUnique(Email $email, UserId $excludeId): void
    {
        if ($this->userRepository->existsByEmailExcludingId($email, $excludeId)) {
            throw new UserAlreadyExistsException("Email {$email} is already in use");
        }
    }

    public function findUserByEmail(Email $email): User
    {
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user) {
            throw new UserNotFoundException("User with email {$email} not found");
        }
        
        return $user;
    }
}