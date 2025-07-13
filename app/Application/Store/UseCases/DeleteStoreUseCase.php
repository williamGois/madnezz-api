<?php

declare(strict_types=1);

namespace App\Application\Store\UseCases;

use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use Illuminate\Support\Facades\Cache;

class DeleteStoreUseCase
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
        
        // Verificar permissões - apenas MASTER e GO podem deletar lojas
        if (!in_array($user->hierarchy_role, ['MASTER', 'GO'])) {
            throw new \Exception('Access denied - insufficient permissions to delete stores');
        }
        
        // Encontrar a loja
        $store = OrganizationUnitModel::find($storeId);
        if (!$store) {
            throw new \InvalidArgumentException('Store not found');
        }
        
        // Verificar se não há dependências (usuários, tarefas, etc.)
        $hasUsers = $store->users()->count() > 0;
        $hasTasks = $store->tasks()->count() > 0;
        
        if ($hasUsers || $hasTasks) {
            throw new \Exception('Cannot delete store: there are users or tasks associated with it');
        }
        
        $organizationId = $store->organization_id;
        $parentId = $store->parent_id;
        
        // Deletar a loja
        $store->delete();
        
        // Invalidar caches relacionados
        $this->invalidateRelatedCaches($organizationId, $parentId);
        
        return [
            'success' => true,
            'message' => 'Store deleted successfully'
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