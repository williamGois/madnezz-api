<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Repositories;

use App\Domain\Organization\Entities\Organization;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Infrastructure\Organization\Eloquent\OrganizationModel;
use App\Infrastructure\Organization\Mappers\OrganizationMapper;

class EloquentOrganizationRepository implements OrganizationRepositoryInterface
{
    public function save(Organization $organization): void
    {
        $data = OrganizationMapper::toEloquent($organization);
        
        OrganizationModel::updateOrCreate(
            ['id' => $data['id']],
            $data
        );
    }

    public function findById(OrganizationId $id): ?Organization
    {
        $model = OrganizationModel::where('id', $id->getValue())->first();
        
        return $model ? OrganizationMapper::toDomain($model) : null;
    }

    public function findByCode(string $code): ?Organization
    {
        $model = OrganizationModel::where('code', $code)->first();
        
        return $model ? OrganizationMapper::toDomain($model) : null;
    }

    public function findActive(): array
    {
        $models = OrganizationModel::where('active', true)->get();
        
        return $models->map(fn(OrganizationModel $model) => OrganizationMapper::toDomain($model))->toArray();
    }

    public function delete(OrganizationId $id): void
    {
        OrganizationModel::where('id', $id->getValue())->delete();
    }
    
    public function findAll(): array
    {
        $models = OrganizationModel::all();
        
        return $models->map(fn(OrganizationModel $model) => OrganizationMapper::toDomain($model))->toArray();
    }
}