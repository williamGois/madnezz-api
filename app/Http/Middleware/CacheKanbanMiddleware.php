<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class CacheKanbanMiddleware
{
    /**
     * Handle an incoming request and cache Kanban board responses
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only cache GET requests to the kanban route
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Check if this is the kanban route
        $routeName = $request->route()?->getName();
        if ($routeName !== 'tasks.kanban' && !str_ends_with($request->path(), 'tasks/kanban')) {
            return $next($request);
        }

        // Get user and organization context
        $user = Auth::user();
        if (!$user) {
            return $next($request);
        }

        $orgContext = $request->get('organization_context');
        if (!$orgContext) {
            return $next($request);
        }

        // Generate cache key based on user, organization unit, and request parameters
        $cacheKey = $this->generateCacheKey($user->id, $orgContext, $request);

        // Check if cached response exists
        $cachedData = Cache::store('redis')
            ->tags(['tasks', 'kanban'])
            ->get($cacheKey);

        if ($cachedData !== null) {
            // Return cached response
            return response()->json($cachedData);
        }

        // Process request
        $response = $next($request);

        // Cache successful responses only
        if ($response->getStatusCode() === 200) {
            $responseData = json_decode($response->getContent(), true);
            
            if ($responseData !== null) {
                // Cache for 5 minutes (300 seconds)
                Cache::store('redis')
                    ->tags(['tasks', 'kanban'])
                    ->put($cacheKey, $responseData, 300);
            }
        }

        return $response;
    }

    /**
     * Generate a unique cache key for the Kanban board
     *
     * @param string $userId
     * @param array $orgContext
     * @param Request $request
     * @return string
     */
    private function generateCacheKey(string $userId, array $orgContext, Request $request): string
    {
        // Base key components
        $keyParts = [
            'kanban',
            $userId,
            $orgContext['organization_unit_id'] ?? 'no-unit'
        ];

        // Add hierarchy filter if present
        $hierarchyFilter = $request->get('hierarchy_filter');
        if ($hierarchyFilter && isset($hierarchyFilter['hierarchy_role'])) {
            $keyParts[] = $hierarchyFilter['hierarchy_role'];
            
            // Add filter hash for complex filters
            if (!empty($hierarchyFilter['filters'])) {
                $keyParts[] = md5(json_encode($hierarchyFilter['filters']));
            }
        }

        // Add any query parameters that affect the response
        $queryParams = $request->except(['organization_context', 'hierarchy_filter']);
        if (!empty($queryParams)) {
            ksort($queryParams);
            $keyParts[] = md5(json_encode($queryParams));
        }

        return implode(':', $keyParts);
    }
}