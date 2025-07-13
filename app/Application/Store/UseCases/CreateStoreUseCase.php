<?php

declare(strict_types=1);

namespace App\Application\Store\UseCases;

use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Facades\Cache;
use Ramsey\Uuid\Uuid;

class CreateStoreUseCase
{
    public function __construct(
        private StoreRepositoryInterface $storeRepository
    ) {}

    public function execute(array $params): array
    {
        $userId = $params['user_id'];
        $user = UserModel::find($userId);
        
        if (!$user) {
            throw new \InvalidArgumentException('User not found');
        }
        
        // Verificar permissões - apenas MASTER e GO podem criar lojas
        if (!in_array($user->hierarchy_role, ['MASTER', 'GO'])) {
            throw new \Exception('Access denied - insufficient permissions to create stores');
        }
        
        // Validar dados obrigatórios
        $this->validateStoreData($params);
        
        // Criar nova loja
        $storeId = OrganizationUnitId::fromString(Uuid::uuid4()->toString());
        $organizationId = OrganizationId::fromString($params['organization_id']);
        
        $store = OrganizationUnit::create(
            $storeId,
            $params['name'],
            $params['code'],
            'store',
            $organizationId,
            $params['parent_id'] ? OrganizationUnitId::fromString($params['parent_id']) : null,
            $params['address'] ?? null,
            $params['city'] ?? null,
            $params['state'] ?? null,
            $params['active'] ?? true
        );
        
        // Salvar no repositório
        $this->storeRepository->save($store);
        
        // Invalidar caches relacionados
        $this->invalidateRelatedCaches($params['organization_id'], $params['parent_id'] ?? null);
        
        return [
            'success' => true,
            'message' => 'Store created successfully',
            'data' => [
                'id' => $store->getId()->toString(),
                'name' => $store->getName(),
                'code' => $store->getCode(),
                'type' => $store->getType(),
                'active' => $store->isActive()
            ]
        ];
    }
    
    private function validateStoreData(array $params): void
    {
        $required = ['name', 'code', 'organization_id'];
        
        foreach ($required as $field) {
            if (empty($params[$field])) {
                throw new \InvalidArgumentException("Field {$field} is required");
            }
        }
        
        // Validar formato do código
        if (!preg_match('/^[A-Z0-9_-]+$/', $params['code'])) {
            throw new \InvalidArgumentException('Store code must contain only uppercase letters, numbers, underscore and dash');
        }
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