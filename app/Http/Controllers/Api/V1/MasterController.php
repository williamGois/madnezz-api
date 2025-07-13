<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MasterController extends BaseController
{
    /**
     * Get master dashboard data
     * 
     * @OA\Get(
     *     path="/api/v1/master/dashboard",
     *     summary="Get master dashboard data",
     *     tags={"Master"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->hierarchy_role !== 'MASTER') {
            return $this->errorResponse('Access denied. Only MASTER users can access this endpoint.', 403);
        }

        // Get organization context from request (added by middleware)
        $context = $request->input('organization_context');

        // Get statistics for MASTER dashboard
        $stats = [
            'total_organizations' => \DB::table('organizations')->count(),
            'active_organizations' => \DB::table('organizations')->where('active', true)->count(),
            'total_stores' => \DB::table('stores')->count(),
            'active_stores' => \DB::table('stores')->where('active', true)->count(),
            'total_users' => \DB::table('users_ddd')->count(),
            'users_by_role' => \DB::table('users_ddd')
                ->select('hierarchy_role', \DB::raw('count(*) as count'))
                ->groupBy('hierarchy_role')
                ->get(),
        ];

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'hierarchy_role' => $user->hierarchy_role,
                'permissions' => $user->permissions ?? ['*'],
                'context_data' => $user->context_data,
            ],
            'context' => $context,
            'statistics' => $stats,
            'message' => 'Welcome to MASTER Dashboard',
        ]);
    }

    /**
     * Test master access without organization context
     */
    public function testAccess(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user) {
            return $this->errorResponse('User not authenticated', 401);
        }

        return $this->successResponse([
            'message' => 'Access test successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'hierarchy_role' => $user->hierarchy_role ?? 'NOT_SET',
                'is_master' => $user->hierarchy_role === 'MASTER',
                'permissions' => $user->permissions ?? [],
                'context_data' => $user->context_data ?? null,
            ],
            'auth_guard' => auth()->getDefaultDriver(),
            'user_table' => $user->getTable(),
        ]);
    }

    /**
     * List all organizations for MASTER
     */
    public function listOrganizations(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->hierarchy_role !== 'MASTER') {
            return $this->errorResponse('Access denied. Only MASTER users can access this endpoint.', 403);
        }

        $organizations = \DB::table('organizations')
            ->select('id', 'name', 'code', 'active', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse([
            'organizations' => $organizations,
            'total' => $organizations->count(),
        ]);
    }

    /**
     * List all stores for MASTER
     */
    public function listStores(Request $request): JsonResponse
    {
        $user = auth()->user();
        
        if (!$user || $user->hierarchy_role !== 'MASTER') {
            return $this->errorResponse('Access denied. Only MASTER users can access this endpoint.', 403);
        }

        $stores = \DB::table('stores as s')
            ->join('organizations as o', 'o.id', '=', 's.organization_id')
            ->leftJoin('users_ddd as u', 'u.id', '=', 's.manager_id')
            ->select(
                's.id',
                's.name',
                's.code',
                's.address',
                's.city',
                's.state',
                's.active',
                'o.name as organization_name',
                'u.name as manager_name',
                's.created_at'
            )
            ->orderBy('s.created_at', 'desc')
            ->get();

        return $this->successResponse([
            'stores' => $stores,
            'total' => $stores->count(),
        ]);
    }
}