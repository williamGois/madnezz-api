<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Mappers;

use App\Domain\Organization\Entities\Organization;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Infrastructure\Organization\Eloquent\OrganizationModel;

class OrganizationMapper
{
    public static function toDomain(OrganizationModel $model): Organization
    {
        return new Organization(
            OrganizationId::fromString($model->id),
            $model->name,
            $model->code,
            $model->active
        );
    }

    public static function toEloquent(Organization $organization): array
    {
        return [
            'id' => $organization->getId()->getValue(),
            'name' => $organization->getName(),
            'code' => $organization->getCode(),
            'active' => $organization->isActive(),
        ];
    }
}