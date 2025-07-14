<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class OrganizationContextMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não autenticado',
            ], 401);
        }

        // Generate cache key
        $cacheKey = "org_context:{$user->id}";

        // Try to get context from cache
        $context = Cache::store('redis')->tags(['organization_context'])->get($cacheKey);

        if ($context) {
            // Use cached context
            $request->merge(['organization_context' => $context]);
            return $next($request);
        }

        // Check if user is MASTER - they have access to everything
        if ($user->hierarchy_role === 'MASTER') {
            // For MASTER users, we create a special context
            $context = [
                'is_master' => true,
                'hierarchy_role' => 'MASTER',
                'organization_id' => $user->context_data['organization_id'] ?? null,
                'organization_name' => 'Master Access',
                'organization_code' => 'MASTER',
                'position_level' => 'MASTER',
                'organization_unit_id' => null,
                'organization_unit_name' => 'All Units',
                'organization_unit_type' => 'ALL',
                'departments' => ['*'], // All departments
                'position_id' => null,
                'permissions' => $user->permissions ?? ['*'],
                'context_data' => $user->context_data,
            ];

            // Cache for 1 hour
            Cache::store('redis')->tags(['organization_context'])->put($cacheKey, $context, 3600);
            
            $request->merge(['organization_context' => $context]);
            
            return $next($request);
        }

        // Get user's organization context from their position
        $position = DB::table('positions')
            ->where('user_id', $user->id)
            ->where('active', true)
            ->first();

        if (!$position) {
            return response()->json([
                'success' => false,
                'message' => 'Usuário não possui posição ativa em nenhuma organização',
            ], 403);
        }

        // Get organization details
        $organization = DB::table('organizations')
            ->where('id', $position->organization_id)
            ->where('active', true)
            ->first();

        if (!$organization) {
            return response()->json([
                'success' => false,
                'message' => 'Organização não encontrada ou inativa',
            ], 403);
        }

        // Get organization unit details
        $organizationUnit = DB::table('organization_units')
            ->where('id', $position->organization_unit_id)
            ->where('active', true)
            ->first();

        if (!$organizationUnit) {
            return response()->json([
                'success' => false,
                'message' => 'Unidade organizacional não encontrada ou inativa',
            ], 403);
        }

        // Get user's departments
        $departments = DB::table('position_departments as pd')
            ->join('departments as d', 'd.id', '=', 'pd.department_id')
            ->where('pd.position_id', $position->id)
            ->where('d.active', true)
            ->select('d.id', 'd.name', 'd.code', 'd.type')
            ->get()
            ->toArray();

        // Get department codes and types for easier access
        $departmentCodes = array_map(fn($d) => $d->code, $departments);
        $departmentTypes = array_unique(array_map(fn($d) => $d->type, $departments));

        // Get parent units hierarchy (for GR and Store Manager context)
        $parentUnits = [];
        $currentUnit = $organizationUnit;
        while ($currentUnit->parent_id) {
            $parentUnit = DB::table('organization_units')
                ->where('id', $currentUnit->parent_id)
                ->first();
            if ($parentUnit) {
                $parentUnits[] = [
                    'id' => $parentUnit->id,
                    'name' => $parentUnit->name,
                    'type' => $parentUnit->type,
                ];
                $currentUnit = $parentUnit;
            } else {
                break;
            }
        }

        // Determine user's store_id if they are a Store Manager
        $storeId = null;
        if ($user->hierarchy_role === 'STORE_MANAGER' && $organizationUnit->type === 'store') {
            $store = DB::table('stores')
                ->where('organization_id', $organization->id)
                ->where('code', $organizationUnit->code)
                ->first();
            $storeId = $store ? $store->id : null;
        }

        // Build organization context
        $context = [
            'user_id' => $user->id,
            'hierarchy_role' => $user->hierarchy_role,
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'organization_code' => $organization->code,
            'position_level' => $position->level,
            'organization_unit_id' => $organizationUnit->id,
            'organization_unit_name' => $organizationUnit->name,
            'organization_unit_type' => $organizationUnit->type,
            'organization_unit_code' => $organizationUnit->code,
            'parent_units' => $parentUnits,
            'departments' => $departments,
            'department_codes' => $departmentCodes,
            'department_types' => $departmentTypes,
            'position_id' => $position->id,
            'store_id' => $storeId,
            'permissions' => $user->permissions ?? [],
            'context_data' => $user->context_data,
        ];

        // Cache for 1 hour
        Cache::store('redis')->tags(['organization_context'])->put($cacheKey, $context, 3600);

        // Add organization context to request
        $request->merge(['organization_context' => $context]);

        return $next($request);
    }

    /**
     * Clear cache for a specific user
     */
    public static function clearCacheForUser(string $userId): void
    {
        $cacheKey = "org_context:{$userId}";
        Cache::store('redis')->tags(['organization_context'])->forget($cacheKey);
    }

    /**
     * Clear all organization context caches
     */
    public static function clearAllCache(): void
    {
        Cache::store('redis')->tags(['organization_context'])->flush();
    }
}