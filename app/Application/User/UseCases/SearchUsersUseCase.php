<?php

declare(strict_types=1);

namespace App\Application\User\UseCases;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SearchUsersUseCase
{
    private const CACHE_TTL = 120; // 2 minutes for search results
    private const MAX_RESULTS = 50;

    public function execute(array $params): array
    {
        $requestingUserId = $params['requesting_user_id'];
        $query = $params['query'] ?? '';
        $limit = min((int) ($params['limit'] ?? 10), self::MAX_RESULTS);
        $filters = $this->extractFilters($params);
        
        if (empty($query) && empty($filters)) {
            return [
                'success' => true,
                'data' => [
                    'users' => [],
                    'query' => $query,
                    'total' => 0
                ]
            ];
        }

        // Get requesting user
        $requestingUser = UserModel::find($requestingUserId);
        if (!$requestingUser) {
            throw new \InvalidArgumentException('Requesting user not found');
        }

        // Build cache key
        $cacheKey = $this->buildCacheKey($requestingUser, $query, $filters, $limit);
        
        // Try cache first
        $cached = Cache::tags(['users', 'users:search'])->get($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Build search query
        $searchQuery = $this->buildSearchQuery($requestingUser, $query, $filters);
        
        // Get results
        $users = $searchQuery->limit($limit)->get();
        $total = $searchQuery->count();

        // Transform results
        $transformedUsers = $users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'hierarchy_role' => $user->hierarchy_role,
                'status' => $user->status,
                'phone' => $user->phone,
                'organization' => $user->organization ? [
                    'id' => $user->organization->id,
                    'name' => $user->organization->name
                ] : null,
                'store' => $user->organizationUnit ? [
                    'id' => $user->organizationUnit->id,
                    'name' => $user->organizationUnit->name
                ] : null,
                'match_score' => $user->match_score ?? null
            ];
        })->toArray();

        $result = [
            'success' => true,
            'data' => [
                'users' => $transformedUsers,
                'query' => $query,
                'total' => $total,
                'limit' => $limit
            ]
        ];

        // Cache the result
        Cache::tags(['users', 'users:search'])->put($cacheKey, $result, self::CACHE_TTL);

        return $result;
    }

    private function extractFilters(array $params): array
    {
        $filters = [];
        $filterKeys = ['role', 'status', 'organization_id', 'store_id'];

        foreach ($filterKeys as $key) {
            if (isset($params[$key]) && $params[$key] !== '') {
                $filters[$key] = $params[$key];
            }
        }

        return $filters;
    }

    private function buildSearchQuery(UserModel $requestingUser, string $query, array $filters)
    {
        $searchQuery = UserModel::with(['organization', 'organizationUnit']);

        // Apply hierarchical access control
        switch ($requestingUser->hierarchy_role) {
            case 'MASTER':
                // MASTER can search all users
                break;
                
            case 'GO':
                // GO can search users in their organization
                $searchQuery->where('organization_id', $requestingUser->organization_id);
                break;
                
            case 'GR':
                // GR can search users in their region
                $searchQuery->where('organization_id', $requestingUser->organization_id)
                           ->whereIn('hierarchy_role', ['GR', 'STORE_MANAGER']);
                break;
                
            case 'STORE_MANAGER':
                // Store managers can only search users in their store
                $searchQuery->where('organization_unit_id', $requestingUser->organization_unit_id);
                break;
        }

        // Apply search query
        if (!empty($query)) {
            $searchTerm = '%' . strtolower($query) . '%';
            
            // Use full-text search if available, otherwise fallback to LIKE
            if (DB::connection()->getDriverName() === 'pgsql') {
                // PostgreSQL full-text search
                $searchQuery->where(function($q) use ($query) {
                    $q->whereRaw("to_tsvector('english', name || ' ' || email) @@ plainto_tsquery('english', ?)", [$query])
                      ->orWhere('name', 'ILIKE', '%' . $query . '%')
                      ->orWhere('email', 'ILIKE', '%' . $query . '%')
                      ->orWhere('phone', 'LIKE', '%' . $query . '%');
                });
            } else {
                // Fallback for other databases
                $searchQuery->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', $searchTerm)
                      ->orWhere('email', 'LIKE', $searchTerm)
                      ->orWhere('phone', 'LIKE', $searchTerm);
                });
            }

            // Add relevance scoring
            $searchQuery->selectRaw('*, 
                CASE 
                    WHEN LOWER(name) = LOWER(?) THEN 100
                    WHEN LOWER(email) = LOWER(?) THEN 90
                    WHEN LOWER(name) LIKE LOWER(?) THEN 80
                    WHEN LOWER(email) LIKE LOWER(?) THEN 70
                    ELSE 50
                END as match_score', 
                [$query, $query, $query . '%', $query . '%']
            );
            
            $searchQuery->orderByDesc('match_score');
        }

        // Apply filters
        if (!empty($filters['role'])) {
            $searchQuery->where('hierarchy_role', $filters['role']);
        }

        if (!empty($filters['status'])) {
            $searchQuery->where('status', $filters['status']);
        }

        if (!empty($filters['organization_id'])) {
            $searchQuery->where('organization_id', $filters['organization_id']);
        }

        if (!empty($filters['store_id'])) {
            $searchQuery->where('organization_unit_id', $filters['store_id']);
        }

        // Exclude deleted users
        $searchQuery->whereNull('deleted_at');

        // Order by relevance if no query, otherwise by name
        if (empty($query)) {
            $searchQuery->orderBy('name', 'asc');
        }

        return $searchQuery;
    }

    private function buildCacheKey(UserModel $user, string $query, array $filters, int $limit): string
    {
        $filterHash = md5(json_encode($filters));
        $queryHash = md5($query);
        
        return sprintf(
            'users:search:%s:%s:%s:%d',
            $user->hierarchy_role,
            $queryHash,
            $filterHash,
            $limit
        );
    }
}