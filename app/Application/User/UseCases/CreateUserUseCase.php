<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Domain\User\ValueObjects\HierarchyRole;
use App\Domain\User\ValueObjects\UserName;
use App\Domain\User\ValueObjects\HashedPassword;
use App\Domain\Shared\ValueObjects\Email;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateUserUseCase
{
    public function __construct(
        private HierarchicalUserRepositoryInterface $userRepository
    ) {}

    public function execute(array $params): array
    {
        $requestingUserId = $params['requesting_user_id'];
        
        // Get requesting user
        $requestingUser = UserModel::find($requestingUserId);
        if (!$requestingUser) {
            throw new \InvalidArgumentException('Requesting user not found');
        }

        // Validate permissions
        $this->validatePermissions($requestingUser, $params);

        DB::beginTransaction();
        try {
            // Check if email already exists
            if (UserModel::where('email', $params['email'])->exists()) {
                throw new \InvalidArgumentException('Email already in use');
            }

            // Create user based on role
            $hierarchicalUser = $this->createUserByRole($params);

            // Save to database
            $userModel = new UserModel();
            $userModel->id = $hierarchicalUser->getId()->toString();
            $userModel->name = $hierarchicalUser->getName()->getValue();
            $userModel->email = $hierarchicalUser->getEmail()->getValue();
            $userModel->password = $hierarchicalUser->getPassword()->getValue();
            $userModel->hierarchy_role = $hierarchicalUser->getHierarchyRole()->getValue();
            $userModel->status = $hierarchicalUser->getStatus()->getValue();
            $userModel->phone = $params['phone'] ?? null;
            $userModel->permissions = json_encode($hierarchicalUser->getPermissions());

            // Set organization and store based on role
            if ($hierarchicalUser->getOrganizationId()) {
                $userModel->organization_id = $hierarchicalUser->getOrganizationId()->toString();
            }

            if ($hierarchicalUser->getStoreId()) {
                $userModel->organization_unit_id = $hierarchicalUser->getStoreId()->toString();
            }

            // Set email as verified if created by admin
            if ($requestingUser->hierarchy_role !== 'STORE_MANAGER') {
                $userModel->email_verified_at = now();
            }

            $userModel->save();

            // Load relationships for response
            $userModel->load(['organization', 'organizationUnit']);

            DB::commit();

            // Invalidate caches
            $this->invalidateCaches($userModel);

            return [
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'id' => $userModel->id,
                    'name' => $userModel->name,
                    'email' => $userModel->email,
                    'hierarchy_role' => $userModel->hierarchy_role,
                    'status' => $userModel->status,
                    'phone' => $userModel->phone,
                    'organization' => $userModel->organization ? [
                        'id' => $userModel->organization->id,
                        'name' => $userModel->organization->name
                    ] : null,
                    'store' => $userModel->organizationUnit ? [
                        'id' => $userModel->organizationUnit->id,
                        'name' => $userModel->organizationUnit->name
                    ] : null,
                    'created_at' => $userModel->created_at->toIso8601String()
                ]
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function validatePermissions(UserModel $requestingUser, array $params): void
    {
        $targetRole = $params['hierarchy_role'];

        switch ($requestingUser->hierarchy_role) {
            case 'MASTER':
                // MASTER can create any user
                break;
                
            case 'GO':
                // GO can create GR and STORE_MANAGER in their organization
                if (!in_array($targetRole, ['GR', 'STORE_MANAGER'])) {
                    throw new \Exception('Insufficient permissions to create this role');
                }
                
                if ($params['organization_id'] !== $requestingUser->organization_id) {
                    throw new \Exception('Cannot create users outside your organization');
                }
                break;
                
            case 'GR':
                // GR can only create STORE_MANAGER in their region
                if ($targetRole !== 'STORE_MANAGER') {
                    throw new \Exception('GR can only create Store Managers');
                }
                
                if ($params['organization_id'] !== $requestingUser->organization_id) {
                    throw new \Exception('Cannot create users outside your organization');
                }
                break;
                
            case 'STORE_MANAGER':
                // Store managers cannot create users
                throw new \Exception('Store Managers cannot create users');
        }
    }

    private function createUserByRole(array $params): HierarchicalUser
    {
        $name = new UserName($params['name']);
        $email = new Email($params['email']);
        $password = new HashedPassword(Hash::make($params['password']));
        $phone = $params['phone'] ?? null;

        switch ($params['hierarchy_role']) {
            case 'MASTER':
                return HierarchicalUser::createMaster($name, $email, $password, $phone);
                
            case 'GO':
                if (empty($params['organization_id'])) {
                    throw new \InvalidArgumentException('Organization ID required for GO role');
                }
                $organizationId = new OrganizationId($params['organization_id']);
                return HierarchicalUser::createGO($name, $email, $password, $organizationId, $phone);
                
            case 'GR':
                if (empty($params['organization_id'])) {
                    throw new \InvalidArgumentException('Organization ID required for GR role');
                }
                $organizationId = new OrganizationId($params['organization_id']);
                return HierarchicalUser::createGR($name, $email, $password, $organizationId, $phone);
                
            case 'STORE_MANAGER':
                if (empty($params['organization_id']) || empty($params['store_id'])) {
                    throw new \InvalidArgumentException('Organization ID and Store ID required for Store Manager role');
                }
                $organizationId = new OrganizationId($params['organization_id']);
                $storeId = new StoreId($params['store_id']);
                return HierarchicalUser::createStoreManager($name, $email, $password, $organizationId, $storeId, $phone);
                
            default:
                throw new \InvalidArgumentException("Invalid hierarchy role: {$params['hierarchy_role']}");
        }
    }

    private function invalidateCaches(UserModel $user): void
    {
        $tags = ['users', 'users:list'];
        
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