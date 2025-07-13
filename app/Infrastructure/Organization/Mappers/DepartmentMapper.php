<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Mappers;

use App\Domain\Organization\Entities\Department;
use App\Domain\Organization\ValueObjects\DepartmentId;
use App\Domain\Organization\ValueObjects\DepartmentType;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Infrastructure\Organization\Eloquent\DepartmentModel;

class DepartmentMapper
{
    public static function toDomain(DepartmentModel $model): Department
    {
        return new Department(
            DepartmentId::fromString($model->id),
            OrganizationId::fromString($model->organization_id),
            new DepartmentType($model->type),
            $model->name,
            $model->description,
            $model->active
        );
    }

    public static function toEloquent(Department $department): array
    {
        return [
            'id' => $department->getId()->getValue(),
            'organization_id' => $department->getOrganizationId()->getValue(),
            'type' => $department->getType()->getValue(),
            'name' => $department->getName(),
            'description' => $department->getDescription(),
            'active' => $department->isActive(),
        ];
    }
}