<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Application\Organization\UseCases\GetOrganizationHierarchyUseCase;
use App\Application\Organization\UseCases\GetHierarchyStatisticsUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class HierarchyController extends Controller
{
    public function __construct(
        private GetOrganizationHierarchyUseCase $getHierarchyUseCase,
        private GetHierarchyStatisticsUseCase $getStatisticsUseCase
    ) {}

    /**
     * Get the organizational hierarchy based on user permissions
     */
    public function getHierarchy(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $hierarchy = $this->getHierarchyUseCase->execute([
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $hierarchy
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the current user's hierarchy information
     */
    public function getMyHierarchy(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Get organization context from request (if available)
            $orgContext = $request->get('organization_context');
            
            $myHierarchy = [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'hierarchy_role' => $user->hierarchy_role,
                ],
                'organization' => null,
                'position' => null,
                'hierarchy_path' => []
            ];
            
            // Add organization details if available
            if ($orgContext) {
                $myHierarchy['organization'] = [
                    'id' => $orgContext['organization_id'] ?? null,
                    'name' => $orgContext['organization_name'] ?? null,
                    'code' => $orgContext['organization_code'] ?? null,
                ];
                
                $myHierarchy['position'] = [
                    'id' => $orgContext['position_id'] ?? null,
                    'level' => $orgContext['position_level'] ?? null,
                    'unit_id' => $orgContext['organization_unit_id'] ?? null,
                    'unit_name' => $orgContext['organization_unit_name'] ?? null,
                    'unit_type' => $orgContext['organization_unit_type'] ?? null,
                    'unit_code' => $orgContext['organization_unit_code'] ?? null,
                ];
                
                // Build hierarchy path
                $hierarchyPath = [];
                
                // Add organization
                if ($orgContext['organization_name'] ?? null) {
                    $hierarchyPath[] = [
                        'level' => 'organization',
                        'name' => $orgContext['organization_name'],
                        'id' => $orgContext['organization_id']
                    ];
                }
                
                // Add parent units if available
                if (isset($orgContext['parent_units']) && is_array($orgContext['parent_units'])) {
                    foreach (array_reverse($orgContext['parent_units']) as $parent) {
                        $hierarchyPath[] = [
                            'level' => $parent['type'] ?? 'unit',
                            'name' => $parent['name'] ?? '',
                            'id' => $parent['id'] ?? null
                        ];
                    }
                }
                
                // Add current unit
                if ($orgContext['organization_unit_name'] ?? null) {
                    $hierarchyPath[] = [
                        'level' => $orgContext['organization_unit_type'] ?? 'unit',
                        'name' => $orgContext['organization_unit_name'],
                        'id' => $orgContext['organization_unit_id']
                    ];
                }
                
                $myHierarchy['hierarchy_path'] = $hierarchyPath;
                
                // Add departments
                $myHierarchy['departments'] = $orgContext['departments'] ?? [];
                
                // Add permissions
                $myHierarchy['permissions'] = $orgContext['permissions'] ?? [];
            }
            
            return response()->json([
                'success' => true,
                'data' => $myHierarchy
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed statistics for the dashboard
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $statistics = $this->getStatisticsUseCase->execute([
                'user_id' => $user->id,
                'period' => $request->get('period', 'today')
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific organization details with all its children
     */
    public function getOrganizationDetails(Request $request, $organizationId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check permissions based on hierarchy
            if (!$this->canAccessOrganization($user, $organizationId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
            }
            
            $details = $this->getHierarchyUseCase->execute([
                'user_id' => $user->id,
                'organization_id' => $organizationId,
                'detailed' => true
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $details
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stores by region
     */
    public function getStoresByRegion(Request $request, $regionId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check permissions
            if (!$this->canAccessRegion($user, $regionId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso negado'
                ], 403);
            }
            
            $stores = $this->getHierarchyUseCase->execute([
                'user_id' => $user->id,
                'region_id' => $regionId,
                'type' => 'stores'
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $stores
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get users count by hierarchy level
     */
    public function getUsersByHierarchy(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $users = $this->getStatisticsUseCase->execute([
                'user_id' => $user->id,
                'type' => 'users_by_hierarchy'
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function canAccessOrganization($user, $organizationId): bool
    {
        // MASTER can access everything
        if ($user->hierarchy_role === 'MASTER') {
            return true;
        }
        
        // GO can access their organization
        if ($user->hierarchy_role === 'GO') {
            return $user->organization_id == $organizationId;
        }
        
        // GR and STORE_MANAGER need to check if organization matches
        $organization = $user->organization;
        return $organization && $organization->id == $organizationId;
    }

    private function canAccessRegion($user, $regionId): bool
    {
        // MASTER and GO can access everything
        if (in_array($user->hierarchy_role, ['MASTER', 'GO'])) {
            return true;
        }
        
        // GR can access their region
        if ($user->hierarchy_role === 'GR') {
            $position = $user->activePosition;
            return $position && $position->organization_unit_id == $regionId;
        }
        
        // STORE_MANAGER can access if their store is in the region
        if ($user->hierarchy_role === 'STORE_MANAGER') {
            $store = $user->store;
            return $store && $store->region_id == $regionId;
        }
        
        return false;
    }
}