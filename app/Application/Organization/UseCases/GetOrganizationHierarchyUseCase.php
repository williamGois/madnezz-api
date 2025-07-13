<?php

namespace App\Application\Organization\UseCases;

use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Domain\Organization\Repositories\PositionRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Domain\User\ValueObjects\UserId;
use Illuminate\Support\Facades\Cache;

class GetOrganizationHierarchyUseCase
{
    public function __construct(
        private OrganizationRepositoryInterface $organizationRepository,
        private OrganizationUnitRepositoryInterface $unitRepository,
        private StoreRepositoryInterface $storeRepository,
        private HierarchicalUserRepositoryInterface $userRepository,
        private PositionRepositoryInterface $positionRepository
    ) {}

    public function execute(array $params): array
    {
        $userId = $params['user_id'];
        $user = $this->userRepository->findById(new UserId($userId));
        
        if (!$user) {
            throw new \Exception('User not found');
        }

        $cacheKey = "hierarchy:{$userId}:{$user->getHierarchyRole()}";
        
        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $hierarchy = [];
            
            switch ($user->getHierarchyRole()) {
                case 'MASTER':
                    $hierarchy = $this->buildCompleteHierarchy();
                    break;
                    
                case 'GO':
                    $hierarchy = $this->buildGOHierarchy($user);
                    break;
                    
                case 'GR':
                    $hierarchy = $this->buildGRHierarchy($user);
                    break;
                    
                case 'STORE_MANAGER':
                    $hierarchy = $this->buildStoreHierarchy($user);
                    break;
            }
            
            return [
                'user_role' => $user->getHierarchyRole(),
                'hierarchy' => $hierarchy,
                'statistics' => $this->calculateStatistics($hierarchy)
            ];
        });
    }

    private function buildCompleteHierarchy(): array
    {
        $organizations = $this->organizationRepository->findAll();
        $hierarchy = [];
        
        foreach ($organizations as $org) {
            $orgData = [
                'id' => $org->getId()->toString(),
                'name' => $org->getName(),
                'code' => $org->getCode(),
                'type' => 'organization',
                'active' => $org->isActive(),
                'children' => []
            ];
            
            // Get organization units (regional levels)
            $units = $this->unitRepository->findByOrganization($org->getId());
            foreach ($units as $unit) {
                if ($unit->getType()->getValue() === 'regional') {
                    $unitData = $this->buildUnitData($unit);
                    $orgData['children'][] = $unitData;
                }
            }
            
            $hierarchy[] = $orgData;
        }
        
        return $hierarchy;
    }

    private function buildGOHierarchy($user): array
    {
        $position = $this->positionRepository->findActiveByUserId($user->getId()->toString());
        if (!$position) {
            return [];
        }
        
        $orgId = $position->getOrganizationId();
        $organization = $this->organizationRepository->findById($orgId);
        
        if (!$organization) {
            return [];
        }
        
        return [$this->buildOrganizationData($organization)];
    }

    private function buildGRHierarchy($user): array
    {
        $position = $this->positionRepository->findActiveByUserId($user->getId()->toString());
        if (!$position) {
            return [];
        }
        
        $unit = $this->unitRepository->findByIdString($position->getOrganizationUnitId());
        if (!$unit || $unit->getType()->getValue() !== 'regional') {
            return [];
        }
        
        return [$this->buildUnitData($unit)];
    }

    private function buildStoreHierarchy($user): array
    {
        if (!$user->getStoreId()) {
            return [];
        }
        
        $store = $this->storeRepository->findById($user->getStoreId()->toString());
        if (!$store) {
            return [];
        }
        
        return [$this->buildStoreData($store)];
    }

    private function buildOrganizationData($organization): array
    {
        $orgData = [
            'id' => $organization->getId()->toString(),
            'name' => $organization->getName(),
            'code' => $organization->getCode(),
            'type' => 'organization',
            'active' => $organization->isActive(),
            'children' => []
        ];
        
        $units = $this->unitRepository->findByOrganization($organization->getId());
        foreach ($units as $unit) {
            if ($unit->getType() === 'regional') {
                $orgData['children'][] = $this->buildUnitData($unit);
            }
        }
        
        return $orgData;
    }

    private function buildUnitData($unit): array
    {
        $unitData = [
            'id' => $unit->getId(),
            'name' => $unit->getName(),
            'type' => 'regional',
            'parent_id' => $unit->getOrganizationId()->toString(),
            'children' => []
        ];
        
        // Get GR (manager)
        $positions = $this->positionRepository->findByOrganizationUnitString($unit->getId());
        foreach ($positions as $position) {
            if ($position->getLevel()->getValue() === 'gr') {
                $gr = $this->userRepository->findById($position->getUserId()->toString());
                if ($gr) {
                    $unitData['manager'] = [
                        'id' => $gr->getId()->toString(),
                        'name' => $gr->getName()->getValue(),
                        'email' => $gr->getEmail()->getValue()
                    ];
                }
                break;
            }
        }
        
        // Get stores in this region
        $stores = $this->storeRepository->findByRegion($unit->getId());
        foreach ($stores as $store) {
            $unitData['children'][] = $this->buildStoreData($store);
        }
        
        return $unitData;
    }

    private function buildStoreData($store): array
    {
        $storeData = [
            'id' => $store->getId()->toString(),
            'name' => $store->getName(),
            'code' => $store->getCode(),
            'type' => 'store',
            'address' => $store->getAddress(),
            'city' => $store->getCity(),
            'state' => $store->getState(),
            'departments' => []
        ];
        
        // Get store manager
        if ($store->getManagerId()) {
            $manager = $this->userRepository->findById($store->getManagerId()->toString());
            if ($manager) {
                $storeData['manager'] = [
                    'id' => $manager->getId()->toString(),
                    'name' => $manager->getName()->getValue(),
                    'email' => $manager->getEmail()->getValue()
                ];
            }
        }
        
        // Get departments
        $departments = $this->getDepartmentsByStore($store->getId());
        $storeData['departments'] = $departments;
        
        return $storeData;
    }

    private function getDepartmentsByStore($storeId): array
    {
        // Default departments for each store
        return [
            ['id' => "{$storeId}_admin", 'name' => 'Administrativo', 'type' => 'administrative'],
            ['id' => "{$storeId}_finance", 'name' => 'Financeiro', 'type' => 'financial'],
            ['id' => "{$storeId}_marketing", 'name' => 'Marketing', 'type' => 'marketing'],
            ['id' => "{$storeId}_operations", 'name' => 'Operações', 'type' => 'operations'],
            ['id' => "{$storeId}_trade", 'name' => 'Comercial', 'type' => 'trade'],
            ['id' => "{$storeId}_macro", 'name' => 'Macro', 'type' => 'macro']
        ];
    }

    private function calculateStatistics($hierarchy): array
    {
        $stats = [
            'total_organizations' => 0,
            'total_regions' => 0,
            'total_stores' => 0,
            'total_users' => 0,
            'active_stores' => 0
        ];
        
        foreach ($hierarchy as $item) {
            $this->countItems($item, $stats);
        }
        
        return $stats;
    }

    private function countItems($item, &$stats): void
    {
        switch ($item['type']) {
            case 'organization':
                $stats['total_organizations']++;
                break;
            case 'regional':
                $stats['total_regions']++;
                if (isset($item['manager'])) {
                    $stats['total_users']++;
                }
                break;
            case 'store':
                $stats['total_stores']++;
                if (isset($item['active']) && $item['active']) {
                    $stats['active_stores']++;
                }
                if (isset($item['manager'])) {
                    $stats['total_users']++;
                }
                break;
        }
        
        if (isset($item['children'])) {
            foreach ($item['children'] as $child) {
                $this->countItems($child, $stats);
            }
        }
    }
}