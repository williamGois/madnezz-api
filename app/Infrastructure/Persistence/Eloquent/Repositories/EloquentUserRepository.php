<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Shared\ValueObjects\Email;
use App\Domain\User\Entities\User;
use App\Domain\User\Repositories\UserRepositoryInterface;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Mappers\UserMapper;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(UserId $id): ?User
    {
        $model = UserModel::find($id->getValue());

        return $model ? UserMapper::toDomain($model) : null;
    }

    public function findByEmail(Email $email): ?User
    {
        $model = UserModel::where('email', $email->getValue())->first();

        return $model ? UserMapper::toDomain($model) : null;
    }

    public function save(User $user): void
    {
        $model = UserModel::find($user->getId()->getValue());

        if ($model) {
            UserMapper::updateEloquentFromDomain($model, $user);
            $model->save();
        } else {
            $model = UserMapper::toEloquent($user);
            $model->save();
        }
    }

    public function delete(User $user): void
    {
        UserModel::where('id', $user->getId()->getValue())->delete();
    }

    public function existsByEmail(Email $email): bool
    {
        return UserModel::where('email', $email->getValue())->exists();
    }

    public function existsByEmailExcludingId(Email $email, UserId $excludeId): bool
    {
        return UserModel::where('email', $email->getValue())
            ->where('id', '!=', $excludeId->getValue())
            ->exists();
    }

    public function findAll(): array
    {
        return UserModel::all()
            ->map(fn(UserModel $model) => UserMapper::toDomain($model))
            ->toArray();
    }

    public function findActiveUsers(): array
    {
        return UserModel::where('status', 'active')
            ->get()
            ->map(fn(UserModel $model) => UserMapper::toDomain($model))
            ->toArray();
    }

    public function countAll(): int
    {
        return UserModel::count();
    }
}