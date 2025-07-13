<?php

declare(strict_types=1);

namespace App\Application\Enterprise\UseCases;

use App\Domain\Enterprise\Repositories\EnterpriseRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Enterprise\Eloquent\EnterpriseModel;
use Illuminate\Support\Facades\Cache;

class ListEnterprisesUseCase
{
    private const CACHE_TTL_MIN = 300; // 5 minutes
    private const CACHE_TTL_MEDIUM = 900; // 15 minutes
    private const CACHE_TTL_MAX = 1800; // 30 minutes
    
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
        
        // Build filters based on user role
        $filters = $this->buildFilters($requestingUser, $params);
        
        // Generate cache key
        $cacheKey = $this->generateCacheKey($filters);
        $cacheTags = $this->getCacheTags($filters);
        
        // Try to get from cache
        $cached = Cache::tags($cacheTags)->get($cacheKey);
        if ($cached !== null) {
            // Increment popularity counter
            $this->incrementPopularity($cacheKey);
            return $cached;
        }
        
        // Get enterprises from repository
        $enterprises = $this->enterpriseRepository->findAll($filters);
        
        // Get statistics
        $statistics = $this->getStatistics($filters, $requestingUser);
        
        // Prepare response
        $response = [
            'success' => true,
            'data' => [
                'enterprises' => array_map(fn($e) => $e->toArray(), $enterprises),
                'pagination' => [
                    'total' => count($enterprises),
                    'page' => $params['page'] ?? 1,
                    'limit' => $params['limit'] ?? 20,
                ],
                'statistics' => $statistics,
                'filters_applied' => $filters,
            ]
        ];
        
        // Cache the response
        $ttl = $this->calculateCacheTTL($cacheKey);
        Cache::tags($cacheTags)->put($cacheKey, $response, $ttl);
        
        return $response;
    }
    
    private function buildFilters(UserModel $user, array $params): array
    {
        $filters = [];
        
        // Apply role-based filtering
        switch ($user->hierarchy_role) {
            case 'GO':
            case 'GR':
                // Can only see enterprises from their organization
                $filters['organization_id'] = $user->organization_id;
                break;
            case 'STORE_MANAGER':
                // Can only see their store's enterprise
                if ($user->organization_unit_id) {
                    $store = $user->organizationUnit;
                    if ($store && $store->enterprise_id) {
                        $filters['enterprise_id'] = $store->enterprise_id;
                    }
                }
                break;
        }
        
        // Apply additional filters
        if (isset($params['status'])) {
            $filters['status'] = $params['status'];
        }
        
        if (isset($params['search'])) {
            $filters['search'] = $params['search'];
        }
        
        if (isset($params['organization_id']) && $user->hierarchy_role === 'MASTER') {
            $filters['organization_id'] = $params['organization_id'];
        }
        
        return $filters;
    }
    
    private function getStatistics(array $filters, UserModel $user): array
    {
        $query = EnterpriseModel::query();
        
        if (isset($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }
        
        $stats = [
            'total_enterprises' => $query->count(),
            'active_enterprises' => (clone $query)->where('status', 'ACTIVE')->count(),
            'inactive_enterprises' => (clone $query)->where('status', 'INACTIVE')->count(),
            'under_construction' => (clone $query)->where('status', 'UNDER_CONSTRUCTION')->count(),
            'suspended_enterprises' => (clone $query)->where('status', 'SUSPENDED')->count(),
        ];
        
        // Add store counts
        if ($user->hierarchy_role === 'MASTER' || $user->hierarchy_role === 'GO') {
            $stats['total_stores'] = EnterpriseModel::query()
                ->when(isset($filters['organization_id']), function ($q) use ($filters) {
                    $q->where('organization_id', $filters['organization_id']);
                })
                ->withCount('stores')
                ->get()
                ->sum('stores_count');
        }
        
        return $stats;
    }
    
    private function generateCacheKey(array $filters): string
    {
        ksort($filters);
        return 'enterprises:list:' . md5(serialize($filters));
    }
    
    private function getCacheTags(array $filters): array
    {
        $tags = ['enterprises', 'enterprises:list'];
        
        if (isset($filters['organization_id'])) {
            $tags[] = "organization:{$filters['organization_id']}";
        }
        
        if (isset($filters['enterprise_id'])) {
            $tags[] = "enterprise:{$filters['enterprise_id']}";
        }
        
        return $tags;
    }
    
    private function incrementPopularity(string $cacheKey): void
    {
        $popularityKey = "cache:popularity:{$cacheKey}";
        $current = Cache::get($popularityKey, 0);
        Cache::put($popularityKey, $current + 1, 86400); // 24 hours
    }
    
    private function calculateCacheTTL(string $cacheKey): int
    {
        $popularityKey = "cache:popularity:{$cacheKey}";
        $hitCount = Cache::get($popularityKey, 0);
        
        if ($hitCount > 20) {
            return self::CACHE_TTL_MAX;
        } elseif ($hitCount > 5) {
            return self::CACHE_TTL_MEDIUM;
        }
        
        return self::CACHE_TTL_MIN;
    }
}