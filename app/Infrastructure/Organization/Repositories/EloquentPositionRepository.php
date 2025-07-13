<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Repositories;

use App\Domain\Organization\Entities\Position;
use App\Domain\Organization\Repositories\PositionRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\Organization\ValueObjects\PositionId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Organization\Eloquent\PositionModel;
use App\Infrastructure\Organization\Mappers\PositionMapper;

class EloquentPositionRepository implements PositionRepositoryInterface
{
    public function save(Position $position): void
    {
        $data = PositionMapper::toEloquent($position);
        
        PositionModel::updateOrCreate(
            ['id' => $data['id']],
            $data
        );
    }

    public function findById(PositionId $id): ?Position
    {
        $model = PositionModel::where('id', $id->getValue())->first();
        
        return $model ? PositionMapper::toDomain($model) : null;
    }

    public function findByUser(UserId $userId): array
    {
        $models = PositionModel::where('user_id', $userId->getValue())
            ->where('active', true)
            ->get();
        
        return $models->map(fn(PositionModel $model) => PositionMapper::toDomain($model))->toArray();
    }

    public function findActiveByUser(UserId $userId): ?Position
    {
        $model = PositionModel::where('user_id', $userId->getValue())
            ->where('active', true)
            ->first();
        
        return $model ? PositionMapper::toDomain($model) : null;
    }

    public function findByOrganizationUnit(OrganizationUnitId $unitId): array
    {
        $models = PositionModel::where('organization_unit_id', $unitId->getValue())
            ->where('active', true)
            ->get();
        
        return $models->map(fn(PositionModel $model) => PositionMapper::toDomain($model))->toArray();
    }

    public function findByOrganization(OrganizationId $organizationId): array
    {
        $models = PositionModel::where('organization_id', $organizationId->getValue())
            ->where('active', true)
            ->get();
        
        return $models->map(fn(PositionModel $model) => PositionMapper::toDomain($model))->toArray();
    }

    public function delete(PositionId $id): void
    {
        PositionModel::where('id', $id->getValue())->delete();
    }
    
    public function findActiveByUserId(string $userId): ?Position
    {
        $model = PositionModel::where('user_id', $userId)
            ->where('active', true)
            ->first();
            
        return $model ? PositionMapper::toDomain($model) : null;
    }
    
    public function findByOrganizationUnitString(string $unitId): array
    {
        $models = PositionModel::where('organization_unit_id', $unitId)
            ->where('active', true)
            ->get();
        
        return $models->map(fn(PositionModel $model) => PositionMapper::toDomain($model))->toArray();
    }
}