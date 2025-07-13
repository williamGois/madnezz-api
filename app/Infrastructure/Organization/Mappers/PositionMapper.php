<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Mappers;

use App\Domain\Organization\Entities\Position;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\Organization\ValueObjects\PositionId;
use App\Domain\Organization\ValueObjects\PositionLevel;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Organization\Eloquent\PositionModel;

class PositionMapper
{
    public static function toDomain(PositionModel $model): Position
    {
        return new Position(
            PositionId::fromString($model->id),
            OrganizationId::fromString($model->organization_id),
            OrganizationUnitId::fromString($model->organization_unit_id),
            UserId::fromString($model->user_id),
            new PositionLevel($model->level),
            $model->title,
            $model->active
        );
    }

    public static function toEloquent(Position $position): array
    {
        return [
            'id' => $position->getId()->getValue(),
            'organization_id' => $position->getOrganizationId()->getValue(),
            'organization_unit_id' => $position->getOrganizationUnitId()->getValue(),
            'user_id' => $position->getUserId()->getValue(),
            'level' => $position->getLevel()->getValue(),
            'title' => $position->getTitle(),
            'active' => $position->isActive(),
        ];
    }
}