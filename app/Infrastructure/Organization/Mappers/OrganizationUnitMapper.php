<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Mappers;

use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\Organization\ValueObjects\OrganizationUnitType;
use App\Infrastructure\Organization\Eloquent\OrganizationUnitModel;

class OrganizationUnitMapper
{
    public static function toDomain(OrganizationUnitModel $model): OrganizationUnit
    {
        return new OrganizationUnit(
            OrganizationUnitId::fromString($model->id),
            OrganizationId::fromString($model->organization_id),
            $model->name,
            $model->code,
            new OrganizationUnitType($model->type),
            $model->parent_id ? OrganizationUnitId::fromString($model->parent_id) : null,
            $model->active
        );
    }

    public static function toEloquent(OrganizationUnit $unit): array
    {
        return [
            'id' => $unit->getId()->getValue(),
            'organization_id' => $unit->getOrganizationId()->getValue(),
            'name' => $unit->getName(),
            'code' => $unit->getCode(),
            'type' => $unit->getType()->getValue(),
            'parent_id' => $unit->getParentId()?->getValue(),
            'active' => $unit->isActive(),
        ];
    }
}