<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entities\User;
use App\Domain\User\ValueObjects\HashedPassword;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\UserName;
use App\Domain\User\ValueObjects\UserStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;

class UserMapper
{
    public static function toDomain(UserModel $model): User
    {
        return new User(
            new UserId($model->id),
            new UserName($model->name),
            new Email($model->email),
            new HashedPassword($model->password),
            new UserStatus($model->status),
            $model->email_verified_at ? new DateTimeImmutable($model->email_verified_at->toDateTimeString()) : null,
            $model->last_login_at ? new DateTimeImmutable($model->last_login_at->toDateTimeString()) : null,
            new DateTimeImmutable($model->created_at->toDateTimeString()),
            new DateTimeImmutable($model->updated_at->toDateTimeString())
        );
    }

    public static function toEloquent(User $user): UserModel
    {
        $model = new UserModel();
        $model->id = $user->getId()->getValue();
        $model->name = $user->getName()->getValue();
        $model->email = $user->getEmail()->getValue();
        $model->password = $user->getPassword()->getValue();
        $model->status = $user->getStatus()->getValue();
        $model->email_verified_at = $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s');
        $model->last_login_at = $user->getLastLoginAt()?->format('Y-m-d H:i:s');
        $model->created_at = $user->getCreatedAt()->format('Y-m-d H:i:s');
        $model->updated_at = $user->getUpdatedAt()->format('Y-m-d H:i:s');

        return $model;
    }

    public static function updateEloquentFromDomain(UserModel $model, User $user): void
    {
        $model->name = $user->getName()->getValue();
        $model->email = $user->getEmail()->getValue();
        $model->password = $user->getPassword()->getValue();
        $model->status = $user->getStatus()->getValue();
        $model->email_verified_at = $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s');
        $model->last_login_at = $user->getLastLoginAt()?->format('Y-m-d H:i:s');
        $model->updated_at = $user->getUpdatedAt()->format('Y-m-d H:i:s');
    }
}