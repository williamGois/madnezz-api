<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Contracts\AuthServiceInterface;
use App\Application\Contracts\TokenServiceInterface;
use App\Application\DTOs\Auth\AuthResultDTO;
use App\Application\DTOs\Auth\LoginUserDTO;
use App\Application\DTOs\Auth\RegisterUserDTO;
use App\Application\DTOs\User\ChangePasswordDTO;
use App\Application\DTOs\User\UpdateUserProfileDTO;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entities\User;
use App\Domain\User\Exceptions\InvalidPasswordException;
use App\Domain\User\Exceptions\UserNotFoundException;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\Services\UserDomainService;
use App\Domain\User\ValueObjects\HashedPassword;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\UserName;
use App\Domain\User\ValueObjects\UserStatus;
use Illuminate\Support\Facades\DB;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserDomainService $userDomainService,
        private TokenServiceInterface $tokenService
    ) {
    }

    public function register(RegisterUserDTO $dto): AuthResultDTO
    {
        return DB::transaction(function () use ($dto) {
            $email = new Email($dto->email);
            $name = new UserName($dto->name);
            $password = HashedPassword::fromPlainText($dto->password);

            $this->userDomainService->ensureUserDoesNotExist($email);

            $user = User::create($name, $email, $password, UserStatus::active());

            $this->userRepository->save($user);

            $token = $this->tokenService->generateToken($user);

            return new AuthResultDTO(
                $user,
                $token,
                'bearer',
                $this->tokenService->getTokenTtl()
            );
        });
    }

    public function login(LoginUserDTO $dto): AuthResultDTO
    {
        return DB::transaction(function () use ($dto) {
            $email = new Email($dto->email);
            $user = $this->userDomainService->findUserByEmail($email);

            if (!$user->canLogin()) {
                throw new InvalidPasswordException('Account is not active');
            }

            if (!$user->verifyPassword($dto->password)) {
                throw new InvalidPasswordException('Invalid credentials');
            }

            $user->recordLogin();
            $this->userRepository->save($user);

            $token = $this->tokenService->generateToken($user);

            return new AuthResultDTO(
                $user,
                $token,
                'bearer',
                $this->tokenService->getTokenTtl()
            );
        });
    }

    public function logout(): void
    {
        $this->tokenService->invalidateToken();
    }

    public function refresh(): AuthResultDTO
    {
        $user = $this->getCurrentUser();
        $token = $this->tokenService->refreshToken();

        return new AuthResultDTO(
            $user,
            $token,
            'bearer',
            $this->tokenService->getTokenTtl()
        );
    }

    public function getCurrentUser(): User
    {
        $user = $this->tokenService->getCurrentUser();
        
        if (!$user) {
            throw new UserNotFoundException('User not found');
        }
        
        return $user;
    }

    public function updateProfile(UserId $userId, UpdateUserProfileDTO $dto): User
    {
        return DB::transaction(function () use ($userId, $dto) {
            $user = $this->userDomainService->ensureUserExists($userId);
            $newEmail = new Email($dto->email);
            $newName = new UserName($dto->name);

            // Check if email is being changed and is unique
            if (!$user->getEmail()->equals($newEmail)) {
                $this->userDomainService->ensureEmailIsUnique($newEmail, $userId);
                $user->updateEmail($newEmail);
            }

            $user->updateName($newName);
            $this->userRepository->save($user);

            return $user;
        });
    }

    public function changePassword(UserId $userId, ChangePasswordDTO $dto): void
    {
        DB::transaction(function () use ($userId, $dto) {
            $user = $this->userDomainService->ensureUserExists($userId);

            if (!$user->verifyPassword($dto->currentPassword)) {
                throw new InvalidPasswordException('Current password is incorrect');
            }

            $newPassword = HashedPassword::fromPlainText($dto->newPassword);
            $user->changePassword($newPassword);
            
            $this->userRepository->save($user);
        });
    }
}