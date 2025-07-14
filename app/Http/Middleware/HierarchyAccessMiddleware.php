<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HierarchyAccessMiddleware
{
    public function handle(Request $request, Closure $next, string $requiredLevel = null, string $requiredDepartment = null)
    {
        $context = $request->get('organization_context');
        
        if (!$context) {
            return response()->json([
                'success' => false,
                'message' => 'Contexto organizacional não encontrado. Certifique-se de que o OrganizationContextMiddleware seja aplicado primeiro.',
            ], 500);
        }

        // MASTER has access to everything
        if (isset($context['is_master']) && $context['is_master']) {
            return $next($request);
        }

        // Check hierarchy level requirement
        if ($requiredLevel) {
            $levelHierarchy = [
                'master' => 0,
                'go' => 1,
                'gr' => 2,  
                'store_manager' => 3,
            ];

            // Use hierarchy_role from context instead of position_level
            $userRole = strtolower($context['hierarchy_role'] ?? '');
            $userLevel = $levelHierarchy[$userRole] ?? 999;
            $requiredLevelNum = $levelHierarchy[$requiredLevel] ?? 999;

            if ($userLevel > $requiredLevelNum) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nível hierárquico insuficiente. Necessário: ' . $requiredLevel,
                ], 403);
            }
        }

        // Check department requirement
        if ($requiredDepartment) {
            // Check if user has the required department in their department codes
            $userDepartments = $context['department_codes'] ?? $context['departments'] ?? [];
            if (!in_array($requiredDepartment, $userDepartments, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado. Departamento necessário: ' . $requiredDepartment,
                ], 403);
            }
        }

        // Check resource-specific access for route parameters
        if ($request->route('store_id')) {
            if (!$this->canAccessStore($context, $request->route('store_id'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado a esta loja',
                ], 403);
            }
        }

        if ($request->route('unit_id')) {
            if (!$this->canAccessOrganizationUnit($context, $request->route('unit_id'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado a esta unidade organizacional',
                ], 403);
            }
        }

        return $next($request);
    }

    private function canAccessStore(array $context, string $storeId): bool
    {
        // MASTER can access everything
        if (isset($context['is_master']) && $context['is_master']) {
            return true;
        }

        $userRole = strtolower($context['hierarchy_role'] ?? '');

        // GO can access all stores in their organization
        if ($userRole === 'go') {
            return true;
        }

        // GR can access stores under their regional unit
        if ($userRole === 'gr') {
            // First check if it's a Store model ID
            $store = DB::table('stores')
                ->where('id', $storeId)
                ->where('organization_id', $context['organization_id'])
                ->first();

            if ($store) {
                // Find the store's organization unit
                $storeUnit = DB::table('organization_units')
                    ->where('organization_id', $context['organization_id'])
                    ->where('code', $store->code)
                    ->where('type', 'store')
                    ->first();
                
                return $storeUnit && $storeUnit->parent_id === $context['organization_unit_id'];
            }

            // If not found as Store, check as organization unit
            $storeUnit = DB::table('organization_units')
                ->where('id', $storeId)
                ->where('type', 'store')
                ->where('active', true)
                ->first();

            if (!$storeUnit) {
                return false;
            }

            // Check if store belongs to GR's regional unit
            return $this->isChildUnit($context['organization_unit_id'], $storeId);
        }

        // Store managers can only access their own store
        if ($userRole === 'store_manager') {
            return isset($context['store_id']) && $context['store_id'] === $storeId;
        }

        return false;
    }

    private function canAccessOrganizationUnit(array $context, string $unitId): bool
    {
        // MASTER can access everything
        if (isset($context['is_master']) && $context['is_master']) {
            return true;
        }

        $userRole = strtolower($context['hierarchy_role'] ?? '');

        // GO can access all units in their organization
        if ($userRole === 'go') {
            return true;
        }

        // GR can access their unit and child units
        if ($userRole === 'gr') {
            return $context['organization_unit_id'] === $unitId || 
                   $this->isChildUnit($context['organization_unit_id'], $unitId);
        }

        // Store managers can only access their own unit
        if ($userRole === 'store_manager') {
            return $context['organization_unit_id'] === $unitId;
        }

        return false;
    }

    private function isChildUnit(string $parentId, string $childId): bool
    {
        // Check if childId is a descendant of parentId
        $currentUnit = DB::table('organization_units')
            ->where('id', $childId)
            ->first();

        while ($currentUnit && $currentUnit->parent_id) {
            if ($currentUnit->parent_id === $parentId) {
                return true;
            }
            
            $currentUnit = DB::table('organization_units')
                ->where('id', $currentUnit->parent_id)
                ->first();
        }

        return false;
    }
}