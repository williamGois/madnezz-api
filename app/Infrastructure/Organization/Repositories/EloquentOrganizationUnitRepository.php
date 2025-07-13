<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Repositories;

use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;
use App\Infrastructure\Organization\Mappers\OrganizationUnitMapper;

class EloquentOrganizationUnitRepository implements OrganizationUnitRepositoryInterface
{
    public function save(OrganizationUnit $unit): void
    {
        $data = OrganizationUnitMapper::toEloquent($unit);
        
        OrganizationUnitModel::updateOrCreate(
            ['id' => $data['id']],
            $data
        );
    }

    public function findById(OrganizationUnitId $id): ?OrganizationUnit
    {
        $model = OrganizationUnitModel::where('id', $id->getValue())->first();
        
        return $model ? OrganizationUnitMapper::toDomain($model) : null;
    }

    public function findByOrganization(OrganizationId $organizationId): array
    {
        $models = OrganizationUnitModel::where('organization_id', $organizationId->getValue())
            ->where('active', true)
            ->get();
        
        return $models->map(fn(OrganizationUnitModel $model) => OrganizationUnitMapper::toDomain($model))->toArray();
    }

    public function findChildren(OrganizationUnitId $parentId): array
    {
        $models = OrganizationUnitModel::where('parent_id', $parentId->getValue())
            ->where('active', true)
            ->get();
        
        return $models->map(fn(OrganizationUnitModel $model) => OrganizationUnitMapper::toDomain($model))->toArray();
    }

    public function findByType(OrganizationId $organizationId, string $type): array
    {
        $models = OrganizationUnitModel::where('organization_id', $organizationId->getValue())
            ->where('type', $type)
            ->where('active', true)
            ->get();
        
        return $models->map(fn(OrganizationUnitModel $model) => OrganizationUnitMapper::toDomain($model))->toArray();
    }

    public function delete(OrganizationUnitId $id): void
    {
        OrganizationUnitModel::where('id', $id->getValue())->delete();
    }
    
    public function findByIdString(string $id): ?OrganizationUnit
    {
        $model = OrganizationUnitModel::where('id', $id)->first();
        
        return $model ? OrganizationUnitMapper::toDomain($model) : null;
    }
}