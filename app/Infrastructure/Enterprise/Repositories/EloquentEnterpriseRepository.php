<?php

declare(strict_types=1);

namespace App\Infrastructure\Enterprise\Repositories;

use App\Domain\Enterprise\Entities\Enterprise;
use App\Domain\Enterprise\Repositories\EnterpriseRepositoryInterface;
use App\Domain\Enterprise\ValueObjects\EnterpriseId;
use App\Domain\Enterprise\ValueObjects\EnterpriseName;
use App\Domain\Enterprise\ValueObjects\EnterpriseCode;
use App\Domain\Enterprise\ValueObjects\EnterpriseStatus;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Infrastructure\Enterprise\Eloquent\EnterpriseModel;

class EloquentEnterpriseRepository implements EnterpriseRepositoryInterface
{
    public function save(Enterprise $enterprise): void
    {
        $model = EnterpriseModel::find($enterprise->getId()->toString());
        
        if (!$model) {
            $model = new EnterpriseModel();
            $model->id = $enterprise->getId()->toString();
        }
        
        $model->fill([
            'name' => $enterprise->getName()->getValue(),
            'code' => $enterprise->getCode()->getValue(),
            'organization_id' => $enterprise->getOrganizationId()->toString(),
            'status' => $enterprise->getStatus()->getValue(),
            'description' => $enterprise->getDescription(),
            'observations' => $enterprise->getObservations(),
            'metadata' => $enterprise->getMetadata(),
        ]);
        
        $model->save();
    }
    
    public function findById(EnterpriseId $id): ?Enterprise
    {
        $model = EnterpriseModel::find($id->toString());
        
        if (!$model) {
            return null;
        }
        
        return $this->toDomain($model);
    }
    
    public function findByCode(EnterpriseCode $code): ?Enterprise
    {
        $model = EnterpriseModel::where('code', $code->getValue())->first();
        
        if (!$model) {
            return null;
        }
        
        return $this->toDomain($model);
    }
    
    public function findByOrganization(OrganizationId $organizationId): array
    {
        $models = EnterpriseModel::where('organization_id', $organizationId->toString())
            ->orderBy('name')
            ->get();
        
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }
    
    public function findAll(array $filters = []): array
    {
        $query = EnterpriseModel::query();
        
        if (isset($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['search'])) {
            $query->search($filters['search']);
        }
        
        $models = $query->orderBy('created_at', 'desc')->get();
        
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }
    
    public function exists(EnterpriseId $id): bool
    {
        return EnterpriseModel::where('id', $id->toString())->exists();
    }
    
    public function existsByCode(EnterpriseCode $code): bool
    {
        return EnterpriseModel::where('code', $code->getValue())->exists();
    }
    
    public function delete(EnterpriseId $id): void
    {
        EnterpriseModel::where('id', $id->toString())->delete();
    }
    
    public function countByOrganization(OrganizationId $organizationId): int
    {
        return EnterpriseModel::where('organization_id', $organizationId->toString())->count();
    }
    
    public function findWithStores(EnterpriseId $id): ?array
    {
        $model = EnterpriseModel::with(['stores', 'organization'])->find($id->toString());
        
        if (!$model) {
            return null;
        }
        
        return [
            'enterprise' => $this->toDomain($model),
            'stores' => $model->stores->toArray(),
            'organization' => $model->organization->toArray(),
            'statistics' => [
                'total_stores' => $model->store_count,
                'active_stores' => $model->active_store_count,
            ],
        ];
    }
    
    public function search(string $query, array $filters = []): array
    {
        $queryBuilder = EnterpriseModel::search($query);
        
        if (isset($filters['organization_id'])) {
            $queryBuilder->where('organization_id', $filters['organization_id']);
        }
        
        if (isset($filters['status'])) {
            $queryBuilder->where('status', $filters['status']);
        }
        
        $models = $queryBuilder->limit(50)->get();
        
        return $models->map(fn($model) => $this->toDomain($model))->toArray();
    }
    
    private function toDomain(EnterpriseModel $model): Enterprise
    {
        return new Enterprise(
            new EnterpriseId($model->id),
            new EnterpriseName($model->name),
            new EnterpriseCode($model->code),
            new OrganizationId($model->organization_id),
            new EnterpriseStatus($model->status),
            $model->description,
            $model->observations,
            $model->metadata ?? [],
            $model->created_at ? $model->created_at->toImmutable() : null,
            $model->updated_at ? $model->updated_at->toImmutable() : null
        );
    }
}