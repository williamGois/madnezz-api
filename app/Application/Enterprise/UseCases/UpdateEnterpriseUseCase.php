<?php

declare(strict_types=1);

namespace App\Application\Enterprise\UseCases;

use App\Domain\Enterprise\Repositories\EnterpriseRepositoryInterface;
use App\Domain\Enterprise\ValueObjects\EnterpriseId;
use App\Domain\Enterprise\ValueObjects\EnterpriseName;
use App\Domain\Enterprise\ValueObjects\EnterpriseStatus;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateEnterpriseUseCase
{
    public function __construct(
        private EnterpriseRepositoryInterface $enterpriseRepository
    ) {}
    
    public function execute(array $params): array
    {
        $requestingUserId = $params['requesting_user_id'];
        $enterpriseId = new EnterpriseId($params['enterprise_id']);
        
        // Get requesting user
        $requestingUser = UserModel::find($requestingUserId);
        if (!$requestingUser) {
            throw new \InvalidArgumentException('Requesting user not found');
        }
        
        // Get enterprise
        $enterprise = $this->enterpriseRepository->findById($enterpriseId);
        if (!$enterprise) {
            throw new \InvalidArgumentException('Enterprise not found');
        }
        
        // Validate permissions
        $this->validatePermissions($requestingUser, $enterprise);
        
        DB::beginTransaction();
        try {
            // Update basic details
            if (isset($params['name']) || isset($params['description']) || isset($params['observations'])) {
                $enterprise->updateDetails(
                    isset($params['name']) ? new EnterpriseName($params['name']) : $enterprise->getName(),
                    $params['description'] ?? $enterprise->getDescription(),
                    $params['observations'] ?? $enterprise->getObservations()
                );
            }
            
            // Update status
            if (isset($params['status'])) {
                $enterprise->updateStatus(new EnterpriseStatus($params['status']));
            }
            
            // Update metadata
            if (isset($params['metadata'])) {
                $enterprise->updateMetadata($params['metadata']);
            }
            
            // Save changes
            $this->enterpriseRepository->save($enterprise);
            
            DB::commit();
            
            // Invalidate caches
            $this->invalidateCaches($enterprise);
            
            return [
                'success' => true,
                'message' => 'Enterprise updated successfully',
                'data' => $enterprise->toArray()
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    private function validatePermissions(UserModel $user, $enterprise): void
    {
        // MASTER can update any enterprise
        if ($user->hierarchy_role === 'MASTER') {
            return;
        }
        
        // GO can only update enterprises in their organization
        if ($user->hierarchy_role === 'GO') {
            if ($user->organization_id !== $enterprise->getOrganizationId()->toString()) {
                throw new \Exception('Cannot update enterprises outside your organization');
            }
            return;
        }
        
        // Others cannot update enterprises
        throw new \Exception('Insufficient permissions to update enterprise');
    }
    
    private function invalidateCaches($enterprise): void
    {
        $tags = [
            'enterprises',
            'enterprises:list',
            "enterprise:{$enterprise->getId()->toString()}",
            "organization:{$enterprise->getOrganizationId()->toString()}",
            'hierarchy:statistics'
        ];
        
        Cache::tags($tags)->flush();
    }
}