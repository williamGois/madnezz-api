<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mappers;

use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\UserName;
use App\Domain\User\ValueObjects\HashedPassword;
use App\Domain\User\ValueObjects\UserStatus;
use App\Domain\User\ValueObjects\HierarchyRole;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Domain\Shared\ValueObjects\Email;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;

class HierarchicalUserMapper
{
    public static function toDomain(UserModel $model): HierarchicalUser
    {
        return new HierarchicalUser(
            new UserId($model->id),
            new UserName($model->name),
            new Email($model->email),
            new HashedPassword($model->password, true), // Already hashed
            new HierarchyRole($model->hierarchy_role ?? 'STORE_MANAGER'),
            new UserStatus($model->status),
            $model->organization_id ? new OrganizationId($model->organization_id) : null,
            $model->store_id ? new StoreId($model->store_id) : null,
            $model->phone,
            $model->permissions ? json_decode($model->permissions, true) : [],
            $model->context_data ? json_decode($model->context_data, true) : null,
            $model->email_verified_at ? new DateTimeImmutable($model->email_verified_at) : null,
            $model->last_login_at ? new DateTimeImmutable($model->last_login_at) : null,
            $model->created_at ? new DateTimeImmutable($model->created_at->format('Y-m-d H:i:s')) : null,
            $model->updated_at ? new DateTimeImmutable($model->updated_at->format('Y-m-d H:i:s')) : null
        );
    }

    public static function toEloquent(HierarchicalUser $user): array
    {
        return [
            'id' => $user->getId()->toString(),
            'name' => $user->getName()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'password' => $user->getPassword()->getValue(),
            'hierarchy_role' => $user->getHierarchyRole()->getValue(),
            'status' => $user->getStatus()->getValue(),
            'organization_id' => $user->getOrganizationId()?->toString(),
            'store_id' => $user->getStoreId()?->toString(),
            'phone' => $user->getPhone(),
            'permissions' => json_encode($user->getPermissions()),
            'context_data' => $user->getContextData() ? json_encode($user->getContextData()) : null,
            'email_verified_at' => $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
            'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    public static function toArray(HierarchicalUser $user): array
    {
        return [
            'id' => $user->getId()->toString(),
            'name' => $user->getName()->getValue(),
            'email' => $user->getEmail()->getValue(),
            'hierarchy_role' => $user->getHierarchyRole()->getValue(),
            'current_role' => $user->getCurrentRole()->getValue(),
            'status' => $user->getStatus()->getValue(),
            'organization_id' => $user->getOrganizationId()?->toString(),
            'store_id' => $user->getStoreId()?->toString(),
            'phone' => $user->getPhone(),
            'permissions' => $user->getPermissions(),
            'context_data' => $user->getContextData(),
            'is_master' => $user->isMaster(),
            'is_go' => $user->isGO(),
            'is_gr' => $user->isGR(),
            'is_store_manager' => $user->isStoreManager(),
            'email_verified_at' => $user->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
            'last_login_at' => $user->getLastLoginAt()?->format('Y-m-d H:i:s'),
            'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}