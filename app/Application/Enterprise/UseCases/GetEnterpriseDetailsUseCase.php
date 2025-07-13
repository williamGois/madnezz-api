<?php

declare(strict_types=1);

namespace App\Application\Enterprise\UseCases;

use App\Domain\Enterprise\Repositories\EnterpriseRepositoryInterface;
use App\Domain\Enterprise\ValueObjects\EnterpriseId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;

class GetEnterpriseDetailsUseCase
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
        
        // Cache key
        $cacheKey = "enterprise:details:{$enterpriseId->toString()}";
        $cacheTags = ['enterprises', "enterprise:{$enterpriseId->toString()}"];
        
        // Try cache first
        $cached = Cache::tags($cacheTags)->get($cacheKey);
        if ($cached !== null) {
            // Validate access
            $this->validateAccess($requestingUser, $cached['enterprise']);
            return $cached;
        }
        
        // Get enterprise with stores
        $data = $this->enterpriseRepository->findWithStores($enterpriseId);
        if (!$data) {
            throw new \InvalidArgumentException('Enterprise not found');
        }
        
        // Validate access
        $this->validateAccess($requestingUser, $data['enterprise']);
        
        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'enterprise' => $data['enterprise']->toArray(),
                'organization' => $data['organization'],
                'stores' => $data['stores'],
                'statistics' => $data['statistics'],
            ]
        ];
        
        // Cache for 10 minutes
        Cache::tags($cacheTags)->put($cacheKey, $response, 600);
        
        return $response;
    }
    
    private function validateAccess(UserModel $user, $enterprise): void
    {
        // MASTER can see all
        if ($user->hierarchy_role === 'MASTER') {
            return;
        }
        
        // GO and GR can see enterprises in their organization
        if (in_array($user->hierarchy_role, ['GO', 'GR'])) {
            if ($user->organization_id !== $enterprise->getOrganizationId()->toString()) {
                throw new \Exception('Cannot access enterprises outside your organization');
            }
            return;
        }
        
        // STORE_MANAGER can only see their store's enterprise
        if ($user->hierarchy_role === 'STORE_MANAGER') {
            if ($user->organizationUnit && 
                $user->organizationUnit->enterprise_id === $enterprise->getId()->toString()) {
                return;
            }
            throw new \Exception('Cannot access this enterprise');
        }
        
        throw new \Exception('Insufficient permissions');
    }
}