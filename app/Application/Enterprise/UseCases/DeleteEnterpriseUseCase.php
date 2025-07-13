<?php

declare(strict_types=1);

namespace App\Application\Enterprise\UseCases;

use App\Domain\Enterprise\Repositories\EnterpriseRepositoryInterface;
use App\Domain\Enterprise\ValueObjects\EnterpriseId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DeleteEnterpriseUseCase
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
        
        // Only MASTER and GO can delete enterprises
        if (!in_array($requestingUser->hierarchy_role, ['MASTER', 'GO'])) {
            throw new \Exception('Insufficient permissions to delete enterprise');
        }
        
        // Get enterprise
        $enterprise = $this->enterpriseRepository->findById($enterpriseId);
        if (!$enterprise) {
            throw new \InvalidArgumentException('Enterprise not found');
        }
        
        // If user is GO, verify they own the enterprise
        if ($requestingUser->hierarchy_role === 'GO' && 
            $enterprise->getOrganizationId()->toString() !== $requestingUser->organization_id) {
            throw new \Exception('Cannot delete enterprises outside your organization');
        }
        
        DB::beginTransaction();
        try {
            // Check if enterprise has stores
            $storeCount = OrganizationUnitModel::where('enterprise_id', $enterpriseId->toString())
                ->where('type', 'STORE')
                ->count();
            
            if ($storeCount > 0) {
                throw new \Exception("Cannot delete enterprise with {$storeCount} associated stores");
            }
            
            // Delete enterprise
            $this->enterpriseRepository->delete($enterpriseId);
            
            DB::commit();
            
            // Invalidate caches
            $this->invalidateCaches($enterprise);
            
            return [
                'success' => true,
                'message' => 'Enterprise deleted successfully'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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