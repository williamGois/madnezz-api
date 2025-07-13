<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ListUsersUseCase
{
    private const CACHE_TTL_MIN = 300; // 5 minutes
    private const CACHE_TTL_MEDIUM = 900; // 15 minutes  
    private const CACHE_TTL_MAX = 1800; // 30 minutes
    private const ITEMS_PER_PAGE = 20;

    public function __construct(
        private HierarchicalUserRepositoryInterface $userRepository
    ) {}

    public function execute(array $params): array
    {
        $userId = $params['user_id'];
        $page = (int) ($params['page'] ?? 1);
        $limit = (int) ($params['limit'] ?? self::ITEMS_PER_PAGE);
        $filters = $this->extractFilters($params);
        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortDirection = $params['sort_direction'] ?? 'desc';
        
        // Get requesting user for permission checks
        $requestingUser = UserModel::find($userId);
        if (!$requestingUser) {
            throw new \InvalidArgumentException('User not found');
        }

        // Build cache key
        $cacheKey = $this->buildCacheKey($requestingUser, $filters, $page, $limit, $sortBy, $sortDirection);
        $cacheTTL = $this->calculateCacheTTL($cacheKey);

        // Try to get from cache
        $cached = Cache::tags(['users', 'users:list'])->get($cacheKey);
        if ($cached) {
            $this->trackCacheHit($cacheKey);
            return $cached;
        }

        // Build query with hierarchical filtering
        $query = $this->buildQuery($requestingUser, $filters);
        
        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        // Get total count
        $total = $query->count();
        
        // Apply pagination
        $users = $query->skip(($page - 1) * $limit)
                      ->take($limit)
                      ->get();

        // Transform results
        $transformedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'hierarchy_role' => $user->hierarchy_role,
                'status' => $user->status,
                'phone' => $user->phone,
                'email_verified' => !is_null($user->email_verified_at),
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name,
                    'type' => $user->organization->type
                ] : null,
                'store' => $user->organizationUnit ? [
                    'id' => $user->organizationUnit->id,
                    'name' => $user->organizationUnit->name,
                    'code' => $user->organizationUnit->code
                ] : null,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String()
            ];
        })->toArray();

        // Calculate pagination
        $totalPages = (int) ceil($total / $limit);
        
        $result = [
            'success' => true,
            'data' => [
                'users' => $transformedUsers,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'statistics' => $this->getStatistics($requestingUser, $filters),
                'filters_applied' => array_keys(array_filter($filters))
            ]
        ];

        // Cache the result with tags for invalidation
        $tags = $this->buildCacheTags($requestingUser, $filters);
        Cache::tags($tags)->put($cacheKey, $result, $cacheTTL);

        return $result;
    }

    private function extractFilters(array $params): array
    {
        $filters = [];
        
        // Basic filters
        $filterKeys = [
            'status', 'hierarchy_role', 'organization_id', 'store_id',
            'search', 'email_verified', 'has_phone', 'created_from',
            'created_to', 'last_login_from', 'last_login_to'
        ];

        foreach ($filterKeys as $key) {
            if (isset($params[$key]) && $params[$key] !== '') {
                $filters[$key] = $params[$key];
            }
        }

        return $filters;
    }

    private function buildQuery(UserModel $requestingUser, array $filters)
    {
        $query = UserModel::with(['organization', 'organizationUnit']);

        // Apply hierarchical access control
        switch ($requestingUser->hierarchy_role) {
            case 'MASTER':
                // MASTER can see all users
                break;
                
            case 'GO':
                // GO can see users in their organization
                $query->where('organization_id', $requestingUser->organization_id);
                break;
                
            case 'GR':
                // GR can see users in their region and stores
                $query->where(function($q) use ($requestingUser) {
                    $q->where('organization_id', $requestingUser->organization_id)
                      ->whereIn('hierarchy_role', ['GR', 'STORE_MANAGER']);
                });
                break;
                
            case 'STORE_MANAGER':
                // Store managers can only see users in their store
                $query->where('organization_unit_id', $requestingUser->organization_unit_id);
                break;
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['hierarchy_role'])) {
            $query->where('hierarchy_role', $filters['hierarchy_role']);
        }

        if (!empty($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        if (!empty($filters['store_id'])) {
            $query->where('organization_unit_id', $filters['store_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function($q) use ($search) {
                $q->where('name', 'ILIKE', $search)
                  ->orWhere('email', 'ILIKE', $search)
                  ->orWhere('phone', 'LIKE', $search);
            });
        }

        if (isset($filters['email_verified'])) {
            if ($filters['email_verified']) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        if (isset($filters['has_phone'])) {
            if ($filters['has_phone']) {
                $query->whereNotNull('phone');
            } else {
                $query->whereNull('phone');
            }
        }

        if (!empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }

        if (!empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }

        if (!empty($filters['last_login_from'])) {
            $query->where('last_login_at', '>=', $filters['last_login_from']);
        }

        if (!empty($filters['last_login_to'])) {
            $query->where('last_login_at', '<=', $filters['last_login_to']);
        }

        return $query;
    }

    private function getStatistics(UserModel $requestingUser, array $filters): array
    {
        $baseQuery = $this->buildQuery($requestingUser, $filters);
        
        return [
            'total_users' => $baseQuery->count(),
            'active_users' => (clone $baseQuery)->where('status', 'ACTIVE')->count(),
            'inactive_users' => (clone $baseQuery)->where('status', 'INACTIVE')->count(),
            'suspended_users' => (clone $baseQuery)->where('status', 'SUSPENDED')->count(),
            'verified_users' => (clone $baseQuery)->whereNotNull('email_verified_at')->count(),
            'by_role' => (clone $baseQuery)->groupBy('hierarchy_role')
                ->selectRaw('hierarchy_role, count(*) as count')
                ->pluck('count', 'hierarchy_role')
                ->toArray()
        ];
    }

    private function buildCacheKey(UserModel $user, array $filters, int $page, int $limit, string $sortBy, string $sortDirection): string
    {
        $filterHash = md5(json_encode($filters));
        return sprintf(
            'users:list:%s:%s:%d:%d:%s:%s',
            $user->hierarchy_role,
            $filterHash,
            $page,
            $limit,
            $sortBy,
            $sortDirection
        );
    }

    private function buildCacheTags(UserModel $user, array $filters): array
    {
        $tags = ['users', 'users:list'];
        
        if (!empty($filters['organization_id'])) {
            $tags[] = "organization:{$filters['organization_id']}";
        }
        
        if (!empty($filters['store_id'])) {
            $tags[] = "store:{$filters['store_id']}";
        }

        if (!empty($filters['hierarchy_role'])) {
            $tags[] = "users:role:{$filters['hierarchy_role']}";
        }

        return $tags;
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

    private function trackCacheHit(string $cacheKey): void
    {
        $popularityKey = "cache:popularity:{$cacheKey}";
        Cache::increment($popularityKey);
        Cache::expire($popularityKey, 86400); // 24 hours
    }
}