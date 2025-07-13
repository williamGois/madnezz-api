<?php

declare(strict_types=1);

namespace App\Application\Contracts;

use App\Application\DTOs\Auth\AuthResultDTO;
use App\Application\DTOs\Auth\LoginUserDTO;
use App\Application\DTOs\Auth\RegisterUserDTO;
use App\Application\DTOs\User\ChangePasswordDTO;
use App\Application\DTOs\User\UpdateUserProfileDTO;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\UserId;

interface AuthServiceInterface
{
    public function register(RegisterUserDTO $dto): AuthResultDTO;
    
    public function login(LoginUserDTO $dto): AuthResultDTO;
    
    public function logout(): void;
    
    public function refresh(): AuthResultDTO;
    
    public function getCurrentUser(): User;
    
    public function updateProfile(UserId $userId, UpdateUserProfileDTO $dto): User;
    
    public function changePassword(UserId $userId, ChangePasswordDTO $dto): void;
}