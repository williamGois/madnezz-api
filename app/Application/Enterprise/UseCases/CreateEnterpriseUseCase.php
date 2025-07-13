<?php

declare(strict_types=1);

namespace App\Application\Enterprise\UseCases;

use App\Domain\Enterprise\Entities\Enterprise;
use App\Domain\Enterprise\Repositories\EnterpriseRepositoryInterface;
use App\Domain\Enterprise\ValueObjects\EnterpriseName;
use App\Domain\Enterprise\ValueObjects\EnterpriseCode;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CreateEnterpriseUseCase
{
    public function __construct(
        private EnterpriseRepositoryInterface $enterpriseRepository
    ) {}
    
    public function execute(array $params): array
    {
        $requestingUserId = $params['requesting_user_id'];
        
        // Get requesting user
        $requestingUser = UserModel::find($requestingUserId);
        if (!$requestingUser) {
            throw new \InvalidArgumentException('Requesting user not found');
        }
        
        // Validate permissions - only MASTER and GO can create enterprises
        if (!in_array($requestingUser->hierarchy_role, ['MASTER', 'GO'])) {
            throw new \Exception('Insufficient permissions to create enterprise');
        }
        
        // If user is GO, they can only create enterprises for their organization
        if ($requestingUser->hierarchy_role === 'GO' && 
            $params['organization_id'] !== $requestingUser->organization_id) {
            throw new \Exception('GO can only create enterprises for their own organization');
        }
        
        DB::beginTransaction();
        try {
            // Generate code if not provided
            $code = isset($params['code']) 
                ? new EnterpriseCode($params['code'])
                : EnterpriseCode::generate($params['name']);
            
            // Check if code already exists
            if ($this->enterpriseRepository->existsByCode($code)) {
                throw new \InvalidArgumentException('Enterprise code already exists');
            }
            
            // Create enterprise
            $enterprise = Enterprise::create(
                new EnterpriseName($params['name']),
                $code,
                new OrganizationId($params['organization_id']),
                $params['description'] ?? null,
                $params['observations'] ?? null,
                $params['metadata'] ?? []
            );
            
            // Save to repository
            $this->enterpriseRepository->save($enterprise);
            
            DB::commit();
            
            // Invalidate caches
            $this->invalidateCaches($enterprise);
            
            return [
                'success' => true,
                'message' => 'Enterprise created successfully',
                'data' => $enterprise->toArray()
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    private function invalidateCaches(Enterprise $enterprise): void
    {
        $tags = [
            'enterprises',
            'enterprises:list',
            "organization:{$enterprise->getOrganizationId()->toString()}",
            'hierarchy:statistics'
        ];
        
        Cache::tags($tags)->flush();
    }
}