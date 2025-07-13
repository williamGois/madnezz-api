<?php

declare(strict_types=1);

namespace App\Domain\User\Entities;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\UserName;
use App\Domain\User\ValueObjects\HashedPassword;
use App\Domain\User\ValueObjects\UserStatus;
use DateTimeImmutable;

class User
{
    private UserId $id;
    private UserName $name;
    private Email $email;
    private HashedPassword $password;
    private UserStatus $status;
    private ?DateTimeImmutable $emailVerifiedAt;
    private ?DateTimeImmutable $lastLoginAt;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        UserId $id,
        UserName $name,
        Email $email,
        HashedPassword $password,
        UserStatus $status,
        ?DateTimeImmutable $emailVerifiedAt = null,
        ?DateTimeImmutable $lastLoginAt = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->email = $email;
        $this->password = $password;
        $this->status = $status;
        $this->emailVerifiedAt = $emailVerifiedAt;
        $this->lastLoginAt = $lastLoginAt;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public static function create(
        UserName $name,
        Email $email,
        HashedPassword $password,
        UserStatus $status = null
    ): self {
        return new self(
            new UserId(),
            $name,
            $email,
            $password,
            $status ?? UserStatus::active(),
            new DateTimeImmutable(), // Auto-verify email for API users
            null,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    public function getId(): UserId
    {
        return $this->id;
    }

    public function getName(): UserName
    {
        return $this->name;
    }

    public function getEmail(): Email
    {
        return $this->email;
    }

    public function getPassword(): HashedPassword
    {
        return $this->password;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateName(UserName $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateEmail(Email $email): void
    {
        $this->email = $email;
        $this->emailVerifiedAt = null; // Reset email verification when email changes
        $this->updatedAt = new DateTimeImmutable();
    }

    public function changePassword(HashedPassword $password): void
    {
        $this->password = $password;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function verifyEmail(): void
    {
        $this->emailVerifiedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->status = UserStatus::active();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->status = UserStatus::inactive();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function suspend(): void
    {
        $this->status = UserStatus::suspended();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function recordLogin(): void
    {
        $this->lastLoginAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return $this->password->verify($plainPassword);
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function canLogin(): bool
    {
        return $this->status->isActive();
    }
}