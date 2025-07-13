<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Repositories;

use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\UserName;
use App\Domain\User\ValueObjects\HashedPassword;
use App\Domain\User\ValueObjects\UserStatus;
use App\Domain\User\ValueObjects\HierarchyRole;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;

class EloquentHierarchicalUserRepository implements HierarchicalUserRepositoryInterface
{
    public function save(HierarchicalUser $user): void
    {
        $userModel = UserModel::find($user->getId()->toString()) ?? new UserModel();
        
        $userModel->id = $user->getId()->toString();
        $userModel->name = $user->getName()->getValue();
        $userModel->email = $user->getEmail()->getValue();
        $userModel->password = $user->getPassword()->getValue();
        $userModel->hierarchy_role = $user->getHierarchyRole()->getValue();
        $userModel->status = $user->getStatus()->getValue();
        $userModel->phone = $user->getPhone();
        $userModel->permissions = json_encode($user->getPermissions());
        
        if ($user->getOrganizationId()) {
            $userModel->organization_id = $user->getOrganizationId()->toString();
        }
        
        if ($user->getStoreId()) {
            $userModel->organization_unit_id = $user->getStoreId()->toString();
        }
        
        if ($user->getEmailVerifiedAt()) {
            $userModel->email_verified_at = $user->getEmailVerifiedAt();
        }
        
        if ($user->getLastLoginAt()) {
            $userModel->last_login_at = $user->getLastLoginAt();
        }
        
        $userModel->save();
    }
    
    public function findById(UserId $id): ?HierarchicalUser
    {
        $userModel = UserModel::find($id->toString());
        
        if (!$userModel) {
            return null;
        }
        
        return $this->toDomain($userModel);
    }
    
    public function findByEmail(Email $email): ?HierarchicalUser
    {
        $userModel = UserModel::where('email', $email->getValue())->first();
        
        if (!$userModel) {
            return null;
        }
        
        return $this->toDomain($userModel);
    }
    
    private function toDomain(UserModel $userModel): HierarchicalUser
    {
        return new HierarchicalUser(
            new UserId($userModel->id),
            new UserName($userModel->name),
            new Email($userModel->email),
            new HashedPassword($userModel->password),
            new HierarchyRole($userModel->hierarchy_role),
            new UserStatus($userModel->status),
            $userModel->organization_id ? new OrganizationId($userModel->organization_id) : null,
            $userModel->organization_unit_id ? new StoreId($userModel->organization_unit_id) : null,
            $userModel->phone,
            json_decode($userModel->permissions ?? '[]', true),
            json_decode($userModel->context_data ?? 'null', true),
            $userModel->email_verified_at,
            $userModel->last_login_at,
            $userModel->created_at,
            $userModel->updated_at
        );
    }
}