<?php

declare(strict_types=1);

namespace App\Infrastructure\Store\Repositories;

use App\Domain\Organization\Entities\Store;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Infrastructure\Store\Eloquent\StoreModel;
use App\Infrastructure\Store\Mappers\StoreMapper;
use App\Infrastructure\Traits\AppliesOrganizationContext;

class EloquentStoreRepository implements StoreRepositoryInterface
{
    use AppliesOrganizationContext;

    public function save(Store $store): void
    {
        $data = StoreMapper::toEloquent($store);
        
        StoreModel::updateOrCreate(
            ['id' => $data['id']],
            $data
        );
    }

    public function findById(StoreId $id): ?Store
    {
        // Check access permission first
        if (!$this->canAccessResource('store', $id->toString())) {
            return null;
        }

        $model = StoreModel::where('id', $id->toString())->first();
        
        return $model ? StoreMapper::toDomain($model) : null;
    }

    public function findByCode(string $code): ?Store
    {
        $query = StoreModel::where('code', $code);
        
        // Apply organization context filter
        $query = $this->applyOrganizationContext($query);
        
        $model = $query->first();
        
        return $model ? StoreMapper::toDomain($model) : null;
    }

    public function findByOrganization(OrganizationId $organizationId): array
    {
        $query = StoreModel::where('organization_id', $organizationId->toString());
        
        // Apply organization context filter
        $query = $this->applyOrganizationContext($query);
        
        $models = $query->get();
        
        return $models->map(fn(StoreModel $model) => StoreMapper::toDomain($model))->toArray();
    }

    public function findActive(): array
    {
        $query = StoreModel::where('active', true);
        
        // Apply organization context filter
        $query = $this->applyOrganizationContext($query);
        
        $models = $query->get();
        
        return $models->map(fn(StoreModel $model) => StoreMapper::toDomain($model))->toArray();
    }

    public function codeExists(string $code): bool
    {
        $query = StoreModel::where('code', $code);
        
        // Apply organization context filter
        $query = $this->applyOrganizationContext($query);
        
        return $query->exists();
    }

    public function delete(StoreId $id): void
    {
        // Check access permission first
        if (!$this->canAccessResource('store', $id->toString())) {
            throw new \DomainException('Access denied to delete this store');
        }

        StoreModel::where('id', $id->toString())->delete();
    }
    
    public function findAll(): array
    {
        $query = StoreModel::query();
        
        // Apply organization context filter
        $query = $this->applyOrganizationContext($query, 'stores');
        
        $models = $query->get();
        
        return $models->map(fn(StoreModel $model) => StoreMapper::toDomain($model))->toArray();
    }
}