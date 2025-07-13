<?php

namespace App\Application\Organization\UseCases;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use App\Infrastructure\Organization\Eloquent\DepartmentModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GetHierarchyStatisticsUseCase
{
    public function __construct()
    {
        // No dependencies needed for now
    }

    public function execute(array $params): array
    {
        $userId = $params['user_id'] ?? null;
        $period = $params['period'] ?? 'today';
        $type = $params['type'] ?? 'general';
        
        if (!$userId) {
            throw new \InvalidArgumentException('User ID is required');
        }
        
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        // For users_by_hierarchy type
        if ($type === 'users_by_hierarchy') {
            return $this->getUsersByHierarchy($user);
        }
        
        // General statistics
        $cacheKey = "hierarchy_stats:{$userId}:{$period}:{$type}";
        
        return Cache::remember($cacheKey, 300, function () use ($user, $period) {
            return $this->buildStatistics($user, $period);
        });
    }
    
    private function buildStatistics(UserModel $user, string $period): array
    {
        $stats = [
            'total_users' => 0,
            'total_organizations' => 0,
            'total_stores' => 0,
            'total_departments' => 0,
            'users_by_role' => [],
            'stores_by_region' => [],
            'period' => $period,
            'generated_at' => now()->toIso8601String()
        ];
        
        try {
            // Base counts regardless of hierarchy
            $stats['total_users'] = UserModel::count();
            $stats['total_organizations'] = DB::table('organizations')->count();
            
            // Check if organization_units table exists
            if (DB::getSchemaBuilder()->hasTable('organization_units')) {
                $stats['total_stores'] = DB::table('organization_units')->where('type', 'store')->count();
            } else {
                $stats['total_stores'] = 0;
            }
            
            // Check if departments table exists
            if (DB::getSchemaBuilder()->hasTable('departments')) {
                $stats['total_departments'] = DB::table('departments')->count();
            } else {
                $stats['total_departments'] = 0;
            }
            
            // Users by role - only if hierarchy_role column exists
            if (DB::getSchemaBuilder()->hasColumn('users', 'hierarchy_role')) {
                $stats['users_by_role'] = UserModel::select('hierarchy_role', DB::raw('count(*) as total'))
                    ->whereNotNull('hierarchy_role')
                    ->groupBy('hierarchy_role')
                    ->pluck('total', 'hierarchy_role')
                    ->toArray();
            }
            
        } catch (\Exception $e) {
            // Fallback to minimal stats if there are errors
            $stats['total_users'] = UserModel::count();
            $stats['total_organizations'] = 1;
            $stats['error'] = 'Some statistics unavailable: ' . $e->getMessage();
        }
        
        return $stats;
    }
    
    private function getUsersByHierarchy(UserModel $user): array
    {
        $query = UserModel::select('hierarchy_role', DB::raw('count(*) as total'))
            ->groupBy('hierarchy_role');
        
        // Apply filters based on user role
        switch ($user->hierarchy_role) {
            case 'GO':
                $query->where('organization_id', $user->organization_id);
                break;
            case 'GR':
                $position = $user->positions()->where('is_active', true)->first();
                if ($position) {
                    $regionId = $position->organization_unit_id;
                    $storeIds = DB::table('organization_units')
                        ->where('parent_id', $regionId)
                        ->where('type', 'store')
                        ->pluck('id');
                    $userIds = DB::table('positions')
                        ->whereIn('organization_unit_id', $storeIds)
                        ->where('is_active', true)
                        ->pluck('user_id');
                    $query->whereIn('id', $userIds);
                }
                break;
            case 'STORE_MANAGER':
                return ['error' => 'Store managers cannot view hierarchy statistics'];
        }
        
        $results = $query->get();
        
        return [
            'hierarchy_counts' => $results->pluck('total', 'hierarchy_role')->toArray(),
            'total' => $results->sum('total')
        ];
    }
    
    private function getDateFilter(string $period): ?string
    {
        switch ($period) {
            case 'today':
                return now()->startOfDay()->toDateTimeString();
            case 'week':
                return now()->subWeek()->toDateTimeString();
            case 'month':
                return now()->subMonth()->toDateTimeString();
            case 'year':
                return now()->subYear()->toDateTimeString();
            default:
                return null;
        }
    }
}