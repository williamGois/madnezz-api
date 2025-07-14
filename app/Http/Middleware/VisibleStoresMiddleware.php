<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class VisibleStoresMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }
        
        // Cache key for visible stores
        $cacheKey = "visible_stores:{$user->id}";
        $cacheTTL = 3600; // 1 hour
        
        // Try to get from cache first
        $visibleStoreIds = Cache::remember($cacheKey, $cacheTTL, function () use ($user) {
            return $this->determineVisibleStores($user);
        });
        
        // Attach to request
        $request->attributes->set('visible_store_ids', $visibleStoreIds);
        
        return $next($request);
    }
    
    /**
     * Determine which stores are visible to the user based on their hierarchy role
     */
    private function determineVisibleStores($user): array
    {
        $hierarchyRole = $user->hierarchy_role;
        $organizationId = $user->organization_id;
        
        // MASTER can see all stores across all organizations
        if ($hierarchyRole === 'MASTER') {
            return $this->getAllStores();
        }
        
        // GO can see all stores in their organization
        if ($hierarchyRole === 'GO') {
            return $this->getOrganizationStores($organizationId);
        }
        
        // GR can see all stores in their region
        if ($hierarchyRole === 'GR') {
            return $this->getRegionStores($user);
        }
        
        // Store Manager can only see their own store
        if ($hierarchyRole === 'STORE_MANAGER') {
            return $this->getUserStore($user);
        }
        
        // Default: no stores visible
        return [];
    }
    
    /**
     * Get all stores (for MASTER users)
     */
    private function getAllStores(): array
    {
        return DB::table('stores')
            ->where('active', true)
            ->pluck('id')
            ->toArray();
    }
    
    /**
     * Get all stores in an organization (for GO users)
     */
    private function getOrganizationStores(string $organizationId): array
    {
        return DB::table('stores')
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->pluck('id')
            ->toArray();
    }
    
    /**
     * Get all stores in a user's region (for GR users)
     */
    private function getRegionStores($user): array
    {
        // Get user's active position to find their region
        $position = DB::table('positions')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
            
        if (!$position || !$position->organization_unit_id) {
            return [];
        }
        
        // Get the region unit
        $regionUnit = DB::table('organization_units')
            ->where('id', $position->organization_unit_id)
            ->where('type', 'regional')
            ->first();
            
        if (!$regionUnit) {
            return [];
        }
        
        // Get all store units that are children of this region
        $storeUnits = DB::table('organization_units')
            ->where('parent_id', $regionUnit->id)
            ->where('type', 'store')
            ->where('active', true)
            ->pluck('code')
            ->toArray();
            
        // Get stores by matching codes
        if (empty($storeUnits)) {
            return [];
        }
        
        return DB::table('stores')
            ->where('organization_id', $regionUnit->organization_id)
            ->whereIn('code', $storeUnits)
            ->where('active', true)
            ->pluck('id')
            ->toArray();
    }
    
    /**
     * Get user's own store (for Store Manager)
     */
    private function getUserStore($user): array
    {
        // Get user's active position
        $position = DB::table('positions')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
            
        if (!$position || !$position->organization_unit_id) {
            return [];
        }
        
        // Get the store unit
        $storeUnit = DB::table('organization_units')
            ->where('id', $position->organization_unit_id)
            ->where('type', 'store')
            ->first();
            
        if (!$storeUnit) {
            return [];
        }
        
        // Get store by code
        $store = DB::table('stores')
            ->where('organization_id', $storeUnit->organization_id)
            ->where('code', $storeUnit->code)
            ->where('active', true)
            ->first();
            
        return $store ? [$store->id] : [];
    }
}