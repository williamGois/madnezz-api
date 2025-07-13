<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Repositories;

use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use App\Infrastructure\Organization\Mappers\OrganizationUnitMapper;

class EloquentStoreRepository implements StoreRepositoryInterface
{
    public function save(OrganizationUnit $store): void
    {
        $data = OrganizationUnitMapper::toEloquent($store);
        
        OrganizationUnitModel::updateOrCreate(
            ['id' => $store->getId()->toString()],
            $data
        );
    }
    
    public function findById(OrganizationUnitId $id): ?OrganizationUnit
    {
        $model = OrganizationUnitModel::find($id->toString());
        
        return $model ? OrganizationUnitMapper::toDomain($model) : null;
    }
    
    public function findByOrganization(OrganizationId $organizationId): array
    {
        $models = OrganizationUnitModel::where('organization_id', $organizationId->toString())->get();
        
        return $models->map(fn(OrganizationUnitModel $model) => OrganizationUnitMapper::toDomain($model))->toArray();
    }
    
    public function findByType(string $type): array
    {
        $models = OrganizationUnitModel::where('type', $type)->get();
        
        return $models->map(fn(OrganizationUnitModel $model) => OrganizationUnitMapper::toDomain($model))->toArray();
    }
    
    public function findByParent(OrganizationUnitId $parentId): array
    {
        $models = OrganizationUnitModel::where('parent_id', $parentId->toString())->get();
        
        return $models->map(fn(OrganizationUnitModel $model) => OrganizationUnitMapper::toDomain($model))->toArray();
    }
    
    public function delete(OrganizationUnitId $id): void
    {
        OrganizationUnitModel::where('id', $id->toString())->delete();
    }
}