<?php

declare(strict_types=1);

namespace App\Application\Store\UseCases;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use Illuminate\Support\Facades\Cache;

class AssignManagerUseCase
{
    public function execute(array $params): array
    {
        $userId = $params['user_id'];
        $storeId = $params['store_id'];
        $managerId = $params['manager_id'] ?? null;
        
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        // Verificar permissões
        if (!in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR'])) {
            throw new \Exception('Access denied - insufficient permissions to assign managers');
        }
        
        // Encontrar a loja
        $store = OrganizationUnitModel::find($storeId);
        if (!$store) {
            throw new \InvalidArgumentException('Store not found');
        }
        
        // Se managerId for null, remove o gerente atual
        if ($managerId === null) {
            $store->update(['manager_id' => null]);
        } else {
            // Verificar se o gerente existe e tem permissão para gerenciar lojas
            $manager = UserModel::find($managerId);
            if (!$manager) {
                throw new \InvalidArgumentException('Manager not found');
            }
            
            if (!in_array($manager->hierarchy_role, ['GR', 'STORE_MANAGER'])) {
                throw new \InvalidArgumentException('User cannot be assigned as store manager');
            }
            
            $store->update(['manager_id' => $managerId]);
        }
        
        // Invalidar caches relacionados
        $this->invalidateRelatedCaches($store->organization_id, $store->parent_id);
        
        return [
            'success' => true,
            'message' => $managerId ? 'Manager assigned successfully' : 'Manager removed successfully',
            'data' => [
                'store_id' => $store->id,
                'store_name' => $store->name,
                'manager_id' => $store->manager_id,
                'manager' => $store->manager ? [
                    'id' => $store->manager->id,
                    'name' => $store->manager->name,
                    'email' => $store->manager->email
                ] : null
            ]
        ];
    }
    
    private function invalidateRelatedCaches(string $organizationId, ?string $parentId): void
    {
        $tags = [
            'stores',
            "organization:{$organizationId}",
            'hierarchy'
        ];
        
        if ($parentId) {
            $tags[] = "parent:{$parentId}";
        }
        
        Cache::tags($tags)->flush();
    }
}