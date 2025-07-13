<?php

declare(strict_types=1);

namespace App\Application\Store\UseCases;

use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ListStoresUseCase
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
        
        // Extrair parâmetros de filtro e paginação
        $filters = $this->extractFilters($params);
        $pagination = $this->extractPagination($params);
        
        // Gerar chave de cache baseada nos filtros e hierarquia do usuário
        $cacheKey = $this->generateCacheKey($user, $filters, $pagination);
        
        // TTL adaptativo baseado na popularidade da consulta
        $ttl = $this->calculateCacheTTL($cacheKey);
        
        return Cache::tags(['stores', 'store-listings'])->remember($cacheKey, $ttl, function () use ($user, $filters, $pagination) {
            return $this->buildStoresList($user, $filters, $pagination);
        });
    }
    
    private function extractFilters(array $params): array
    {
        return [
            'organization_id' => $params['organization_id'] ?? null,
            'parent_id' => $params['parent_id'] ?? null,
            'department_type' => $params['department_type'] ?? null,
            'active' => $params['active'] ?? null,
            'search' => $params['search'] ?? null,
            'city' => $params['city'] ?? null,
            'state' => $params['state'] ?? null,
            'manager_id' => $params['manager_id'] ?? null,
            'has_manager' => $params['has_manager'] ?? null
        ];
    }
    
    private function extractPagination(array $params): array
    {
        return [
            'page' => max(1, intval($params['page'] ?? 1)),
            'limit' => min(100, max(1, intval($params['limit'] ?? 20))),
            'sort_by' => $params['sort_by'] ?? 'name',
            'sort_direction' => in_array($params['sort_direction'] ?? 'asc', ['asc', 'desc']) 
                ? $params['sort_direction'] : 'asc'
        ];
    }
    
    private function generateCacheKey(UserModel $user, array $filters, array $pagination): string
    {
        $keyData = [
            'user_role' => $user->hierarchy_role,
            'user_org' => $user->organization_id,
            'filters' => array_filter($filters), // Remove null values
            'pagination' => $pagination
        ];
        
        return 'stores_list:' . md5(json_encode($keyData));
    }
    
    private function calculateCacheTTL(string $cacheKey): int
    {
        // Verificar popularidade da consulta (quantas vezes foi executada recentemente)
        $popularityKey = "popularity:{$cacheKey}";
        $hitCount = Cache::get($popularityKey, 0);
        
        // Incrementar contador de popularidade
        Cache::put($popularityKey, $hitCount + 1, 3600); // TTL de 1 hora para contador
        
        // TTL adaptativo: consultas populares ficam mais tempo em cache
        return $hitCount > 10 ? 3600 : 900; // 1 hora vs 15 minutos
    }
    
    private function buildStoresList(UserModel $user, array $filters, array $pagination): array
    {
        $query = OrganizationUnitModel::with(['manager', 'organization', 'parent'])
            ->where('type', 'store');
        
        // Aplicar filtros de segurança baseados na hierarquia
        $query = $this->applyHierarchicalFilters($query, $user);
        
        // Aplicar filtros específicos
        $query = $this->applyCustomFilters($query, $filters);
        
        // Aplicar ordenação
        $query = $this->applySorting($query, $pagination);
        
        // Contar total para paginação
        $total = $query->count();
        
        // Aplicar paginação
        $offset = ($pagination['page'] - 1) * $pagination['limit'];
        $stores = $query->offset($offset)->limit($pagination['limit'])->get();
        
        return [
            'success' => true,
            'data' => [
                'stores' => $this->formatStores($stores),
                'pagination' => [
                    'current_page' => $pagination['page'],
                    'per_page' => $pagination['limit'],
                    'total' => $total,
                    'total_pages' => ceil($total / $pagination['limit']),
                    'has_next' => ($pagination['page'] * $pagination['limit']) < $total,
                    'has_prev' => $pagination['page'] > 1
                ],
                'filters_applied' => array_filter($filters),
                'user_permissions' => $this->getUserPermissions($user)
            ]
        ];
    }
    
    private function applyHierarchicalFilters($query, UserModel $user)
    {
        switch ($user->hierarchy_role) {
            case 'MASTER':
                // MASTER vê todas as lojas
                break;
                
            case 'GO':
                // GO vê apenas lojas da sua organização
                $query->where('organization_id', $user->organization_id);
                break;
                
            case 'GR':
                // GR vê apenas lojas da sua região
                $position = $user->positions()->where('is_active', true)->first();
                if ($position) {
                    $regionId = $position->organization_unit_id;
                    $query->where('parent_id', $regionId);
                }
                break;
                
            case 'STORE_MANAGER':
                // STORE_MANAGER vê apenas sua própria loja
                $position = $user->positions()->where('is_active', true)->first();
                if ($position) {
                    $query->where('id', $position->organization_unit_id);
                }
                break;
        }
        
        return $query;
    }
    
    private function applyCustomFilters($query, array $filters)
    {
        if ($filters['organization_id']) {
            $query->where('organization_id', $filters['organization_id']);
        }
        
        if ($filters['parent_id']) {
            $query->where('parent_id', $filters['parent_id']);
        }
        
        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }
        
        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                  ->orWhere('code', 'ILIKE', "%{$search}%")
                  ->orWhere('city', 'ILIKE', "%{$search}%");
            });
        }
        
        if ($filters['city']) {
            $query->where('city', 'ILIKE', "%{$filters['city']}%");
        }
        
        if ($filters['state']) {
            $query->where('state', $filters['state']);
        }
        
        if ($filters['manager_id']) {
            $query->where('manager_id', $filters['manager_id']);
        }
        
        if (isset($filters['has_manager'])) {
            if ($filters['has_manager']) {
                $query->whereNotNull('manager_id');
            } else {
                $query->whereNull('manager_id');
            }
        }
        
        if ($filters['department_type']) {
            $query->whereHas('departments', function ($q) use ($filters) {
                $q->where('type', $filters['department_type']);
            });
        }
        
        return $query;
    }
    
    private function applySorting($query, array $pagination)
    {
        $sortBy = $pagination['sort_by'];
        $direction = $pagination['sort_direction'];
        
        // Mapeamento de campos permitidos para ordenação
        $allowedSortFields = [
            'name' => 'name',
            'code' => 'code',
            'city' => 'city',
            'state' => 'state',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at'
        ];
        
        if (isset($allowedSortFields[$sortBy])) {
            $query->orderBy($allowedSortFields[$sortBy], $direction);
        } else {
            $query->orderBy('name', 'asc');
        }
        
        // Sempre adicionar ID como segundo critério para consistência
        $query->orderBy('id', 'asc');
        
        return $query;
    }
    
    private function formatStores($stores): array
    {
        return $stores->map(function ($store) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'type' => $store->type,
                'active' => $store->active,
                'address' => $store->address,
                'city' => $store->city,
                'state' => $store->state,
                'organization' => $store->organization ? [
                    'id' => $store->organization->id,
                    'name' => $store->organization->name,
                    'code' => $store->organization->code
                ] : null,
                'parent' => $store->parent ? [
                    'id' => $store->parent->id,
                    'name' => $store->parent->name,
                    'type' => $store->parent->type
                ] : null,
                'manager' => $store->manager ? [
                    'id' => $store->manager->id,
                    'name' => $store->manager->name,
                    'email' => $store->manager->email
                ] : null,
                'created_at' => $store->created_at->toISOString(),
                'updated_at' => $store->updated_at->toISOString()
            ];
        })->toArray();
    }
    
    private function getUserPermissions(UserModel $user): array
    {
        return [
            'can_create' => in_array($user->hierarchy_role, ['MASTER', 'GO']),
            'can_edit' => in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR']),
            'can_delete' => in_array($user->hierarchy_role, ['MASTER', 'GO']),
            'can_assign_managers' => in_array($user->hierarchy_role, ['MASTER', 'GO', 'GR']),
            'hierarchy_role' => $user->hierarchy_role
        ];
    }
}