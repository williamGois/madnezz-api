<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateUserUseCase
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
        $this->validatePermissions($requestingUser, $targetUser, $params);

        DB::beginTransaction();
        try {
            // Update allowed fields
            if (isset($params['name'])) {
                $targetUser->name = $params['name'];
            }

            if (isset($params['email']) && $params['email'] !== $targetUser->email) {
                // Check if new email is already in use
                if (UserModel::where('email', $params['email'])->where('id', '!=', $targetUserId)->exists()) {
                    throw new \InvalidArgumentException('Email already in use');
                }
                $targetUser->email = $params['email'];
                $targetUser->email_verified_at = null; // Reset verification
            }

            if (isset($params['phone'])) {
                $targetUser->phone = $params['phone'];
            }

            if (isset($params['password'])) {
                $targetUser->password = Hash::make($params['password']);
            }

            if (isset($params['status'])) {
                $this->validateStatusChange($requestingUser, $targetUser, $params['status']);
                $targetUser->status = $params['status'];
            }

            // Handle organization/store assignment changes
            if (isset($params['organization_id'])) {
                $this->validateOrganizationChange($requestingUser, $targetUser, $params['organization_id']);
                $targetUser->organization_id = $params['organization_id'];
            }

            if (isset($params['store_id'])) {
                $this->validateStoreChange($requestingUser, $targetUser, $params['store_id']);
                $targetUser->organization_unit_id = $params['store_id'] ?: null;
            }

            $targetUser->save();

            // Load relationships
            $targetUser->load(['organization', 'organizationUnit']);

            DB::commit();

            // Invalidate caches
            $this->invalidateCaches($targetUser);

            return [
                'success' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'id' => $targetUser->id,
                    'name' => $targetUser->name,
                    'email' => $targetUser->email,
                    'hierarchy_role' => $targetUser->hierarchy_role,
                    'status' => $targetUser->status,
                    'phone' => $targetUser->phone,
                    'email_verified' => !is_null($targetUser->email_verified_at),
                    'organization' => $targetUser->organization ? [
                        'id' => $targetUser->organization->id,
                        'name' => $targetUser->organization->name
                    ] : null,
                    'store' => $targetUser->organizationUnit ? [
                        'id' => $targetUser->organizationUnit->id,
                        'name' => $targetUser->organizationUnit->name
                    ] : null,
                    'updated_at' => $targetUser->updated_at->toIso8601String()
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function validatePermissions(UserModel $requestingUser, UserModel $targetUser, array $params): void
    {
        // Users can update their own basic info (name, phone, password)
        if ($requestingUser->id === $targetUser->id) {
            $allowedSelfUpdate = ['name', 'phone', 'password', 'email'];
            $requestedFields = array_keys($params);
            $disallowedFields = array_diff($requestedFields, [...$allowedSelfUpdate, 'requesting_user_id', 'user_id']);
            
            if (!empty($disallowedFields)) {
                throw new \Exception('Cannot update restricted fields on your own account');
            }
            return;
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
            throw new \Exception('Cannot update users at the same or higher hierarchy level');
        }

        // Additional checks based on role
        switch ($requestingUser->hierarchy_role) {
            case 'GO':
                if ($targetUser->organization_id !== $requestingUser->organization_id) {
                    throw new \Exception('Cannot update users outside your organization');
                }
                break;
                
            case 'GR':
                if ($targetUser->organization_id !== $requestingUser->organization_id) {
                    throw new \Exception('Cannot update users outside your organization');
                }
                if ($targetUser->hierarchy_role !== 'STORE_MANAGER') {
                    throw new \Exception('GR can only update Store Managers');
                }
                break;
                
            case 'STORE_MANAGER':
                throw new \Exception('Store Managers cannot update other users');
        }
    }

    private function validateStatusChange(UserModel $requestingUser, UserModel $targetUser, string $newStatus): void
    {
        $validStatuses = ['ACTIVE', 'INACTIVE', 'SUSPENDED'];
        
        if (!in_array($newStatus, $validStatuses)) {
            throw new \InvalidArgumentException('Invalid status');
        }

        // Cannot deactivate yourself
        if ($requestingUser->id === $targetUser->id && $newStatus !== 'ACTIVE') {
            throw new \Exception('Cannot deactivate your own account');
        }
    }

    private function validateOrganizationChange(UserModel $requestingUser, UserModel $targetUser, string $newOrgId): void
    {
        if ($requestingUser->hierarchy_role !== 'MASTER') {
            throw new \Exception('Only MASTER users can change organization assignments');
        }

        // Verify organization exists
        if (!OrganizationModel::where('id', $newOrgId)->exists()) {
            throw new \InvalidArgumentException('Organization not found');
        }

        // MASTER users cannot be assigned to organizations
        if ($targetUser->hierarchy_role === 'MASTER' && $newOrgId) {
            throw new \Exception('MASTER users cannot be assigned to organizations');
        }
    }

    private function validateStoreChange(UserModel $requestingUser, UserModel $targetUser, ?string $newStoreId): void
    {
        // Only certain roles can be assigned to stores
        if (!in_array($targetUser->hierarchy_role, ['GR', 'STORE_MANAGER'])) {
            throw new \Exception("Users with role {$targetUser->hierarchy_role} cannot be assigned to stores");
        }

        if ($newStoreId) {
            // Verify store exists
            $store = OrganizationUnitModel::find($newStoreId);
            if (!$store) {
                throw new \InvalidArgumentException('Store not found');
            }

            // Verify store belongs to user's organization
            if ($store->organization_id !== $targetUser->organization_id) {
                throw new \Exception('Store does not belong to user\'s organization');
            }
        }
    }

    private function invalidateCaches(UserModel $user): void
    {
        $tags = ['users', 'users:list', "user:{$user->id}"];
        
        if ($user->organization_id) {
            $tags[] = "organization:{$user->organization_id}";
        }
        
        if ($user->organization_unit_id) {
            $tags[] = "store:{$user->organization_unit_id}";
        }

        $tags[] = "users:role:{$user->hierarchy_role}";

        Cache::tags($tags)->flush();
    }
}