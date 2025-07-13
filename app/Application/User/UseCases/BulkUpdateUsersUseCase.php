<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkUpdateUsersUseCase
{
    private const MAX_BULK_SIZE = 100;

    public function execute(array $params): array
    {
        $requestingUserId = $params['requesting_user_id'];
        $userIds = $params['user_ids'] ?? [];
        $action = $params['action'];
        
        // Validate bulk size
        if (count($userIds) > self::MAX_BULK_SIZE) {
            throw new \InvalidArgumentException('Maximum bulk size is ' . self::MAX_BULK_SIZE . ' users');
        }

        if (empty($userIds)) {
            throw new \InvalidArgumentException('No users selected');
        }

        // Get requesting user
        $requestingUser = UserModel::find($requestingUserId);
        if (!$requestingUser) {
            throw new \InvalidArgumentException('Requesting user not found');
        }

        // Validate action
        $validActions = ['activate', 'deactivate', 'suspend', 'delete'];
        if (!in_array($action, $validActions)) {
            throw new \InvalidArgumentException('Invalid bulk action');
        }

        // Get target users
        $targetUsers = UserModel::whereIn('id', $userIds)->get();
        
        if ($targetUsers->count() !== count($userIds)) {
            throw new \InvalidArgumentException('Some users not found');
        }

        // Validate permissions for each user
        $this->validateBulkPermissions($requestingUser, $targetUsers, $action);

        DB::beginTransaction();
        try {
            $results = [
                'success' => [],
                'failed' => [],
                'skipped' => []
            ];

            foreach ($targetUsers as $targetUser) {
                try {
                    $result = $this->processUser($requestingUser, $targetUser, $action);
                    
                    if ($result['status'] === 'success') {
                        $results['success'][] = [
                            'id' => $targetUser->id,
                            'name' => $targetUser->name,
                            'message' => $result['message']
                        ];
                    } else {
                        $results['skipped'][] = [
                            'id' => $targetUser->id,
                            'name' => $targetUser->name,
                            'reason' => $result['message']
                        ];
                    }
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'id' => $targetUser->id,
                        'name' => $targetUser->name,
                        'error' => $e->getMessage()
                    ];
                }
            }

            DB::commit();

            // Invalidate caches
            $this->invalidateBulkCaches($targetUsers);

            return [
                'success' => true,
                'message' => 'Bulk operation completed',
                'data' => [
                    'total' => count($userIds),
                    'successful' => count($results['success']),
                    'failed' => count($results['failed']),
                    'skipped' => count($results['skipped']),
                    'results' => $results
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function validateBulkPermissions(UserModel $requestingUser, $targetUsers, string $action): void
    {
        // Cannot perform bulk operations on yourself
        if ($targetUsers->contains('id', $requestingUser->id)) {
            throw new \Exception('Cannot perform bulk operations on your own account');
        }

        // Check hierarchy permissions
        $roleHierarchy = [
            'MASTER' => 4,
            'GO' => 3,
            'GR' => 2,
            'STORE_MANAGER' => 1
        ];

        $requestingLevel = $roleHierarchy[$requestingUser->hierarchy_role] ?? 0;

        foreach ($targetUsers as $targetUser) {
            $targetLevel = $roleHierarchy[$targetUser->hierarchy_role] ?? 0;
            
            if ($requestingLevel <= $targetLevel) {
                throw new \Exception("Cannot perform bulk operations on users at the same or higher hierarchy level (User: {$targetUser->name})");
            }

            // Organization-based checks
            switch ($requestingUser->hierarchy_role) {
                case 'GO':
                    if ($targetUser->organization_id !== $requestingUser->organization_id) {
                        throw new \Exception("Cannot perform operations on users outside your organization (User: {$targetUser->name})");
                    }
                    break;
                    
                case 'GR':
                    if ($targetUser->organization_id !== $requestingUser->organization_id) {
                        throw new \Exception("Cannot perform operations on users outside your organization (User: {$targetUser->name})");
                    }
                    if ($targetUser->hierarchy_role !== 'STORE_MANAGER') {
                        throw new \Exception("GR can only perform operations on Store Managers (User: {$targetUser->name})");
                    }
                    break;
                    
                case 'STORE_MANAGER':
                    throw new \Exception('Store Managers cannot perform bulk operations');
            }
        }
    }

    private function processUser(UserModel $requestingUser, UserModel $targetUser, string $action): array
    {
        switch ($action) {
            case 'activate':
                if ($targetUser->status === 'ACTIVE') {
                    return ['status' => 'skipped', 'message' => 'Already active'];
                }
                $targetUser->status = 'ACTIVE';
                $targetUser->save();
                return ['status' => 'success', 'message' => 'Activated'];
                
            case 'deactivate':
                if ($targetUser->status === 'INACTIVE') {
                    return ['status' => 'skipped', 'message' => 'Already inactive'];
                }
                $targetUser->status = 'INACTIVE';
                $targetUser->save();
                return ['status' => 'success', 'message' => 'Deactivated'];
                
            case 'suspend':
                if ($targetUser->status === 'SUSPENDED') {
                    return ['status' => 'skipped', 'message' => 'Already suspended'];
                }
                $targetUser->status = 'SUSPENDED';
                $targetUser->save();
                return ['status' => 'success', 'message' => 'Suspended'];
                
            case 'delete':
                if ($targetUser->deleted_at !== null) {
                    return ['status' => 'skipped', 'message' => 'Already deleted'];
                }
                
                // Check dependencies before deleting
                if ($targetUser->managedStores()->exists()) {
                    return ['status' => 'skipped', 'message' => 'User manages stores'];
                }
                
                if ($targetUser->createdTasks()->where('status', '!=', 'DONE')->exists()) {
                    return ['status' => 'skipped', 'message' => 'User has active tasks'];
                }
                
                $targetUser->status = 'DELETED';
                $targetUser->deleted_at = now();
                $targetUser->save();
                return ['status' => 'success', 'message' => 'Deleted'];
                
            default:
                throw new \InvalidArgumentException('Invalid action');
        }
    }

    private function invalidateBulkCaches($targetUsers): void
    {
        $tags = ['users', 'users:list'];
        $organizations = [];
        $stores = [];
        $roles = [];

        foreach ($targetUsers as $user) {
            $tags[] = "user:{$user->id}";
            
            if ($user->organization_id && !in_array($user->organization_id, $organizations)) {
                $organizations[] = $user->organization_id;
                $tags[] = "organization:{$user->organization_id}";
            }
            
            if ($user->organization_unit_id && !in_array($user->organization_unit_id, $stores)) {
                $stores[] = $user->organization_unit_id;
                $tags[] = "store:{$user->organization_unit_id}";
            }
            
            if (!in_array($user->hierarchy_role, $roles)) {
                $roles[] = $user->hierarchy_role;
                $tags[] = "users:role:{$user->hierarchy_role}";
            }
        }

        Cache::tags($tags)->flush();
    }
}