<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeleteUserUseCase
{
    public function execute(array $params): array
    {
        $requestingUserId = $params['requesting_user_id'];
        $targetUserId = $params['user_id'];
        
        // Get users
        $requestingUser = UserModel::find($requestingUserId);
        $targetUser = UserModel::find($targetUserId);
        
        if (!$requestingUser || !$targetUser) {
            throw new \InvalidArgumentException('User not found');
        }

        // Validate permissions
        $this->validatePermissions($requestingUser, $targetUser);

        DB::beginTransaction();
        try {
            // Store user data for cache invalidation
            $organizationId = $targetUser->organization_id;
            $storeId = $targetUser->organization_unit_id;
            $role = $targetUser->hierarchy_role;

            // Soft delete the user
            $targetUser->status = 'DELETED';
            $targetUser->deleted_at = now();
            $targetUser->save();

            DB::commit();

            // Invalidate caches
            $this->invalidateCaches($targetUser->id, $organizationId, $storeId, $role);

            return [
                'success' => true,
                'message' => 'User deleted successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function validatePermissions(UserModel $requestingUser, UserModel $targetUser): void
    {
        // Cannot delete yourself
        if ($requestingUser->id === $targetUser->id) {
            throw new \Exception('Cannot delete your own account');
        }

        // Hierarchy-based permissions
        $roleHierarchy = [
            'MASTER' => 4,
            'GO' => 3,
            'GR' => 2,
            'STORE_MANAGER' => 1
        ];

        $requestingLevel = $roleHierarchy[$requestingUser->hierarchy_role] ?? 0;
        $targetLevel = $roleHierarchy[$targetUser->hierarchy_role] ?? 0;

        if ($requestingLevel <= $targetLevel) {
            throw new \Exception('Cannot delete users at the same or higher hierarchy level');
        }

        // Additional checks based on role
        switch ($requestingUser->hierarchy_role) {
            case 'GO':
                if ($targetUser->organization_id !== $requestingUser->organization_id) {
                    throw new \Exception('Cannot delete users outside your organization');
                }
                break;
                
            case 'GR':
                if ($targetUser->organization_id !== $requestingUser->organization_id) {
                    throw new \Exception('Cannot delete users outside your organization');
                }
                if ($targetUser->hierarchy_role !== 'STORE_MANAGER') {
                    throw new \Exception('GR can only delete Store Managers');
                }
                break;
                
            case 'STORE_MANAGER':
                throw new \Exception('Store Managers cannot delete users');
        }

        // Check if user has dependencies
        $this->checkDependencies($targetUser);
    }

    private function checkDependencies(UserModel $user): void
    {
        // Check if user is a manager of any store
        if ($user->managedStores()->exists()) {
            throw new \Exception('Cannot delete user who manages stores. Please reassign stores first.');
        }

        // Check if user has created tasks
        if ($user->createdTasks()->where('status', '!=', 'DONE')->exists()) {
            throw new \Exception('Cannot delete user with active tasks. Please reassign or complete tasks first.');
        }

        // Check if user is assigned to tasks
        if ($user->assignedTasks()->where('status', '!=', 'DONE')->exists()) {
            throw new \Exception('Cannot delete user assigned to active tasks. Please reassign tasks first.');
        }
    }

    private function invalidateCaches(string $userId, ?string $organizationId, ?string $storeId, string $role): void
    {
        $tags = ['users', 'users:list', "user:{$userId}"];
        
        if ($organizationId) {
            $tags[] = "organization:{$organizationId}";
        }
        
        if ($storeId) {
            $tags[] = "store:{$storeId}";
        }

        $tags[] = "users:role:{$role}";

        Cache::tags($tags)->flush();
    }
}