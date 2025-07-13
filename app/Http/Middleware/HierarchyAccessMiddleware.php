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

        // Check hierarchy level requirement
        if ($requiredLevel) {
            $levelHierarchy = [
                'go' => 1,
                'gr' => 2,  
                'store_manager' => 3,
            ];

            $userLevel = $levelHierarchy[$context['position_level']] ?? 999;
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
            if (!in_array($requiredDepartment, $context['departments'], true)) {
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
        // GO can access all stores
        if ($context['position_level'] === 'go') {
            return true;
        }

        // GR can access stores under their regional unit
        if ($context['position_level'] === 'gr') {
            $store = DB::table('organization_units')
                ->where('id', $storeId)
                ->where('type', 'store')
                ->where('active', true)
                ->first();

            if (!$store) {
                return false;
            }

            // Check if store belongs to GR's regional unit
            return $this->isChildUnit($context['organization_unit_id'], $storeId);
        }

        // Store managers can only access their own store
        if ($context['position_level'] === 'store_manager') {
            return $context['organization_unit_id'] === $storeId;
        }

        return false;
    }

    private function canAccessOrganizationUnit(array $context, string $unitId): bool
    {
        // GO can access all units
        if ($context['position_level'] === 'go') {
            return true;
        }

        // GR can access their unit and child units
        if ($context['position_level'] === 'gr') {
            return $context['organization_unit_id'] === $unitId || 
                   $this->isChildUnit($context['organization_unit_id'], $unitId);
        }

        // Store managers can only access their own unit
        return $context['organization_unit_id'] === $unitId;
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