<?php

namespace App\Application\Organization\UseCases;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\DB;

class GetSimpleStatisticsUseCase
{
    public function execute(array $params): array
    {
        $userId = $params['user_id'] ?? null;
        $period = $params['period'] ?? 'today';
        
        if (!$userId) {
            throw new \InvalidArgumentException('User ID is required');
        }
        
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        return [
            'success' => true,
            'data' => [
                'total_users' => UserModel::count(),
                'total_organizations' => DB::table('organizations')->count(),
                'total_stores' => 5, // placeholder
                'total_departments' => 10, // placeholder
                'users_by_role' => [
                    'MASTER' => 1,
                    'GO' => 0,
                    'GR' => 0,
                    'STORE_MANAGER' => 0
                ],
                'period' => $period,
                'generated_at' => now()->toIso8601String(),
                'user_role' => $user->hierarchy_role ?? 'STORE_MANAGER'
            ]
        ];
    }
}