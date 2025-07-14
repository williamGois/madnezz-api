<?php

declare(strict_types=1);

namespace App\Infrastructure\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Request;

trait AppliesOrganizationContext
{
    /**
     * Apply organization context filters to a query
     */
    protected function applyOrganizationContext(Builder $query, string $table = ''): Builder
    {
        $context = Request::input('organization_context');
        
        if (!$context) {
            return $query;
        }

        $tablePrefix = $table ? $table . '.' : '';

        // MASTER has access to everything
        if (isset($context['is_master']) && $context['is_master']) {
            return $query;
        }

        // Apply organization filter
        if (isset($context['organization_id'])) {
            $query->where($tablePrefix . 'organization_id', $context['organization_id']);
        }

        // Apply hierarchy-based filters
        switch ($context['hierarchy_role'] ?? null) {
            case 'GO':
                // GO sees everything in their organization
                break;
                
            case 'GR':
                // GR sees only stores in their region
                if ($context['organization_unit_type'] === 'regional' && $table === 'stores') {
                    // Get stores that belong to this region
                    $query->whereExists(function ($subQuery) use ($context) {
                        $subQuery->select('id')
                            ->from('organization_units')
                            ->whereColumn('organization_units.code', 'stores.code')
                            ->where('organization_units.parent_id', $context['organization_unit_id'])
                            ->where('organization_units.type', 'store');
                    });
                }
                break;
                
            case 'STORE_MANAGER':
                // Store Manager sees only their store
                if ($table === 'stores' && isset($context['store_id'])) {
                    $query->where($tablePrefix . 'id', $context['store_id']);
                }
                break;
        }

        return $query;
    }

    /**
     * Apply user context filters
     */
    protected function applyUserContext(Builder $query, string $table = ''): Builder
    {
        $context = Request::input('organization_context');
        
        if (!$context) {
            return $query;
        }

        $tablePrefix = $table ? $table . '.' : '';

        // MASTER has access to everything
        if (isset($context['is_master']) && $context['is_master']) {
            return $query;
        }

        // Apply organization filter
        if (isset($context['organization_id'])) {
            $query->where($tablePrefix . 'organization_id', $context['organization_id']);
        }

        // Apply hierarchy-based filters for users
        switch ($context['hierarchy_role'] ?? null) {
            case 'GO':
                // GO sees all users in their organization
                break;
                
            case 'GR':
                // GR sees users in their region and subordinate stores
                if ($context['organization_unit_type'] === 'regional') {
                    $query->where(function ($q) use ($context, $tablePrefix) {
                        // Users directly in the region
                        $q->whereExists(function ($subQuery) use ($context, $tablePrefix) {
                            $subQuery->select('id')
                                ->from('positions')
                                ->whereColumn('positions.user_id', $tablePrefix . 'id')
                                ->where('positions.organization_unit_id', $context['organization_unit_id'])
                                ->where('positions.active', true);
                        })
                        // Users in stores under this region
                        ->orWhereExists(function ($subQuery) use ($context, $tablePrefix) {
                            $subQuery->select('p.id')
                                ->from('positions as p')
                                ->join('organization_units as ou', 'ou.id', '=', 'p.organization_unit_id')
                                ->whereColumn('p.user_id', $tablePrefix . 'id')
                                ->where('ou.parent_id', $context['organization_unit_id'])
                                ->where('ou.type', 'store')
                                ->where('p.active', true);
                        });
                    });
                }
                break;
                
            case 'STORE_MANAGER':
                // Store Manager sees only users in their store
                if ($context['organization_unit_type'] === 'store') {
                    $query->whereExists(function ($subQuery) use ($context, $tablePrefix) {
                        $subQuery->select('id')
                            ->from('positions')
                            ->whereColumn('positions.user_id', $tablePrefix . 'id')
                            ->where('positions.organization_unit_id', $context['organization_unit_id'])
                            ->where('positions.active', true);
                    });
                }
                break;
        }

        return $query;
    }

    /**
     * Check if user can access a specific resource based on context
     */
    protected function canAccessResource(string $resourceType, $resourceId): bool
    {
        $context = Request::input('organization_context');
        
        if (!$context) {
            return false;
        }

        // MASTER can access everything
        if (isset($context['is_master']) && $context['is_master']) {
            return true;
        }

        // Implement specific access rules based on resource type
        switch ($resourceType) {
            case 'store':
                return $this->canAccessStore($resourceId, $context);
            case 'user':
                return $this->canAccessUser($resourceId, $context);
            case 'organization_unit':
                return $this->canAccessOrganizationUnit($resourceId, $context);
            default:
                return false;
        }
    }

    private function canAccessStore($storeId, array $context): bool
    {
        // Implementation depends on hierarchy role
        switch ($context['hierarchy_role'] ?? null) {
            case 'GO':
                // GO can access all stores in their organization
                return true;
                
            case 'GR':
                // GR can access stores in their region
                // Check if store's organization unit has the GR's unit as parent
                $store = \DB::table('stores')->where('id', $storeId)->first();
                if (!$store) return false;
                
                $storeUnit = \DB::table('organization_units')
                    ->where('organization_id', $store->organization_id)
                    ->where('code', $store->code)
                    ->where('type', 'store')
                    ->first();
                    
                return $storeUnit && $storeUnit->parent_id === $context['organization_unit_id'];
                
            case 'STORE_MANAGER':
                // Store Manager can only access their own store
                return isset($context['store_id']) && $storeId === $context['store_id'];
                
            default:
                return false;
        }
    }

    private function canAccessUser($userId, array $context): bool
    {
        // Check if user belongs to accessible organization units
        $userPosition = \DB::table('positions')
            ->where('user_id', $userId)
            ->where('organization_id', $context['organization_id'])
            ->where('active', true)
            ->first();
            
        if (!$userPosition) return false;

        return $this->canAccessOrganizationUnit($userPosition->organization_unit_id, $context);
    }

    private function canAccessOrganizationUnit($unitId, array $context): bool
    {
        switch ($context['hierarchy_role'] ?? null) {
            case 'GO':
                return true;
                
            case 'GR':
                // Can access their own unit and child units
                if ($unitId === $context['organization_unit_id']) return true;
                
                // Check if unit is a child of GR's unit
                $unit = \DB::table('organization_units')->where('id', $unitId)->first();
                return $unit && $unit->parent_id === $context['organization_unit_id'];
                
            case 'STORE_MANAGER':
                // Can only access their own unit
                return $unitId === $context['organization_unit_id'];
                
            default:
                return false;
        }
    }
}