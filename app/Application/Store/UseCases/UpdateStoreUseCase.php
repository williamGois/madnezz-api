<?php

declare(strict_types=1);

namespace App\Application\Store\UseCases;

use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use Illuminate\Support\Facades\Cache;

class UpdateStoreUseCase
{
    public function __construct(
        private StoreRepositoryInterface $storeRepository
    ) {}

    public function execute(array $params): array
    {
        $userId = $params['user_id'];
        $storeId = $params['store_id'];
        
        $user = UserModel::find($userId);
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        // Verificar permissões
        if (!in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR'])) {
            throw new \Exception('Access denied - insufficient permissions to update stores');
        }
        
        // Encontrar a loja
        $store = OrganizationUnitModel::find($storeId);
        if (!$store) {
            throw new \InvalidArgumentException('Store not found');
        }
        
        // Atualizar campos permitidos
        $allowedFields = ['name', 'address', 'city', 'state', 'active'];
        $updates = array_intersect_key($params, array_flip($allowedFields));
        
        if (empty($updates)) {
            throw new \InvalidArgumentException('No valid fields to update');
        }
        
        $store->update($updates);
        
        // Invalidar caches relacionados
        $this->invalidateRelatedCaches($store->organization_id, $store->parent_id);
        
        return [
            'success' => true,
            'message' => 'Store updated successfully',
            'data' => [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'type' => $store->type,
                'active' => $store->active,
                'address' => $store->address,
                'city' => $store->city,
                'state' => $store->state,
                'updated_at' => $store->updated_at->toISOString()
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