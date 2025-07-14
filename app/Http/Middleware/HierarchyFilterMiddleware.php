<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HierarchyFilterMiddleware
{
    /**
     * Handle an incoming request and inject hierarchy filters based on user role
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Get organization context from request (injected by OrganizationContextMiddleware)
        $orgContext = $request->get('organization_context');
        
        if (!$orgContext) {
            return response()->json([
                'success' => false,
                'message' => 'Organization context not found',
            ], 403);
        }

        // Initialize hierarchy filter array
        $hierarchyFilter = [];

        // Apply filters based on hierarchy role
        switch ($orgContext['hierarchy_role']) {
            case 'MASTER':
                // MASTER can see everything - no filters applied
                $hierarchyFilter = [
                    'hierarchy_role' => 'MASTER',
                    'filters' => []
                ];
                break;

            case 'GO':
                // GO can see everything within their organization
                $hierarchyFilter = [
                    'hierarchy_role' => 'GO',
                    'filters' => [
                        'organization_id' => $orgContext['organization_id']
                    ]
                ];
                break;

            case 'GR':
                // GR can see their region and all child units (stores)
                $childUnitIds = $this->getChildUnitIds($orgContext['organization_unit_id']);
                
                $hierarchyFilter = [
                    'hierarchy_role' => 'GR',
                    'filters' => [
                        'organization_unit_id' => $orgContext['organization_unit_id'],
                        'include_child_units' => true,
                        'child_unit_ids' => $childUnitIds,
                        'all_unit_ids' => array_merge([$orgContext['organization_unit_id']], $childUnitIds)
                    ]
                ];
                break;

            case 'STORE_MANAGER':
                // Store Manager can only see their specific store
                $hierarchyFilter = [
                    'hierarchy_role' => 'STORE_MANAGER',
                    'filters' => [
                        'organization_unit_id' => $orgContext['organization_unit_id'],
                        'store_id' => $orgContext['store_id'] ?? null
                    ]
                ];
                break;

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid hierarchy role',
                ], 403);
        }

        // Merge hierarchy filter into request
        $request->merge([
            'hierarchy_filter' => $hierarchyFilter
        ]);

        return $next($request);
    }

    /**
     * Get all child unit IDs for a given parent unit
     *
     * @param string $parentUnitId
     * @return array
     */
    private function getChildUnitIds(string $parentUnitId): array
    {
        // Recursively get all child units
        $childUnits = DB::table('organization_units')
            ->where('parent_id', $parentUnitId)
            ->where('active', true)
            ->pluck('id')
            ->toArray();

        $allChildUnits = $childUnits;

        // Recursively get children of children
        foreach ($childUnits as $childId) {
            $grandChildren = $this->getChildUnitIds($childId);
            $allChildUnits = array_merge($allChildUnits, $grandChildren);
        }

        return array_unique($allChildUnits);
    }
}