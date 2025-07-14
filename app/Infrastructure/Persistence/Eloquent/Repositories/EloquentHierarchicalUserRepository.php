<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\HierarchyRole;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Domain\Shared\ValueObjects\Email;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Mappers\HierarchicalUserMapper;
use App\Infrastructure\Traits\AppliesOrganizationContext;

class EloquentHierarchicalUserRepository implements HierarchicalUserRepositoryInterface
{
    use AppliesOrganizationContext;
    public function findById(UserId $id): ?HierarchicalUser
    {
        // Check access permission first
        if (!$this->canAccessResource('user', $id->toString())) {
            return null;
        }
        
        $model = UserModel::find($id->toString());
        
        return $model ? HierarchicalUserMapper::toDomain($model) : null;
    }

    public function findByEmail(Email $email): ?HierarchicalUser
    {
        $model = UserModel::where('email', $email->getValue())->first();
        
        return $model ? HierarchicalUserMapper::toDomain($model) : null;
    }

    public function findByHierarchyRole(HierarchyRole $role): array
    {
        $query = UserModel::where('hierarchy_role', $role->getValue());
        
        // Apply user context filter
        $query = $this->applyUserContext($query, 'users_ddd');
        
        $models = $query->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        $query = UserModel::where('organization_id', $organizationId->toString());
        
        // Apply user context filter
        $query = $this->applyUserContext($query, 'users_ddd');
        
        $models = $query->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findByStoreId(StoreId $storeId): array
    {
        $models = UserModel::where('store_id', $storeId->toString())->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findSubordinates(HierarchicalUser $user): array
    {
        $query = UserModel::query();
        
        // Based on hierarchy role, find subordinates
        switch ($user->getHierarchyRole()->getValue()) {
            case 'MASTER':
                // MASTER can see everyone
                break;
            case 'GO':
                // GO can see GR and STORE_MANAGER in their organization
                $query->where('organization_id', $user->getOrganizationId()->toString())
                      ->whereIn('hierarchy_role', ['GR', 'STORE_MANAGER']);
                break;
            case 'GR':
                // GR can see STORE_MANAGER in their organization
                $query->where('organization_id', $user->getOrganizationId()->toString())
                      ->where('hierarchy_role', 'STORE_MANAGER');
                break;
            case 'STORE_MANAGER':
                // STORE_MANAGER has no subordinates in this hierarchy
                return [];
        }
        
        $models = $query->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findMasterUsers(): array
    {
        $models = UserModel::where('hierarchy_role', 'MASTER')->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findGOsByOrganization(OrganizationId $organizationId): array
    {
        $models = UserModel::where('organization_id', $organizationId->toString())
                           ->where('hierarchy_role', 'GO')
                           ->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findGRsByOrganization(OrganizationId $organizationId): array
    {
        $models = UserModel::where('organization_id', $organizationId->toString())
                           ->where('hierarchy_role', 'GR')
                           ->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findStoreManagersByOrganization(OrganizationId $organizationId): array
    {
        $models = UserModel::where('organization_id', $organizationId->toString())
                           ->where('hierarchy_role', 'STORE_MANAGER')
                           ->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findStoreManagersByStore(StoreId $storeId): array
    {
        $models = UserModel::where('store_id', $storeId->toString())
                           ->where('hierarchy_role', 'STORE_MANAGER')
                           ->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findUsersWithAccessToOrganization(OrganizationId $organizationId): array
    {
        // MASTER users + users in the organization
        $models = UserModel::where('hierarchy_role', 'MASTER')
                           ->orWhere('organization_id', $organizationId->toString())
                           ->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function findUsersWithAccessToStore(StoreId $storeId): array
    {
        $storeModel = \App\Infrastructure\Organization\Eloquent\StoreModel::find($storeId->toString());
        if (!$storeModel) {
            return [];
        }

        // MASTER users + users in the store's organization + store managers of the specific store
        $models = UserModel::where('hierarchy_role', 'MASTER')
                           ->orWhere('organization_id', $storeModel->organization_id)
                           ->orWhere('store_id', $storeId->toString())
                           ->get();
        
        return $models->map(fn(UserModel $model) => HierarchicalUserMapper::toDomain($model))->toArray();
    }

    public function save(HierarchicalUser $user): void
    {
        $data = HierarchicalUserMapper::toEloquent($user);
        
        UserModel::updateOrCreate(
            ['id' => $user->getId()->toString()],
            $data
        );
    }

    public function delete(UserId $id): void
    {
        UserModel::where('id', $id->toString())->delete();
    }

    public function exists(UserId $id): bool
    {
        return UserModel::where('id', $id->toString())->exists();
    }

    public function emailExists(Email $email): bool
    {
        return UserModel::where('email', $email->getValue())->exists();
    }

    public function countByRole(HierarchyRole $role): int
    {
        return UserModel::where('hierarchy_role', $role->getValue())->count();
    }

    public function countByOrganization(OrganizationId $organizationId): int
    {
        return UserModel::where('organization_id', $organizationId->toString())->count();
    }

    public function countByStore(StoreId $storeId): int
    {
        return UserModel::where('store_id', $storeId->toString())->count();
    }
    
}