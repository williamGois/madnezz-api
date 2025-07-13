<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Repositories;

use App\Domain\Organization\Entities\Department;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use App\Domain\Organization\ValueObjects\DepartmentId;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Infrastructure\Organization\Eloquent\DepartmentModel;
use App\Infrastructure\Organization\Mappers\DepartmentMapper;

class EloquentDepartmentRepository implements DepartmentRepositoryInterface
{
    public function save(Department $department): void
    {
        $data = DepartmentMapper::toEloquent($department);
        
        DepartmentModel::updateOrCreate(
            ['id' => $data['id']],
            $data
        );
    }

    public function findById(DepartmentId $id): ?Department
    {
        $model = DepartmentModel::where('id', $id->getValue())->first();
        
        return $model ? DepartmentMapper::toDomain($model) : null;
    }

    public function findByOrganization(OrganizationId $organizationId): array
    {
        $models = DepartmentModel::where('organization_id', $organizationId->getValue())
            ->where('active', true)
            ->get();
        
        return $models->map(fn(DepartmentModel $model) => DepartmentMapper::toDomain($model))->toArray();
    }

    public function findByType(OrganizationId $organizationId, string $type): ?Department
    {
        $model = DepartmentModel::where('organization_id', $organizationId->getValue())
            ->where('type', $type)
            ->where('active', true)
            ->first();
        
        return $model ? DepartmentMapper::toDomain($model) : null;
    }

    public function delete(DepartmentId $id): void
    {
        DepartmentModel::where('id', $id->getValue())->delete();
    }
}