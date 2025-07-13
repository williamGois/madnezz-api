<?php

declare(strict_types=1);

namespace App\Infrastructure\Organization\Mappers;

use App\Domain\Organization\Entities\Store;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Organization\Eloquent\StoreModel;
use DateTimeImmutable;

class StoreMapper
{
    public static function toDomain(StoreModel $model): Store
    {
        return new Store(
            new StoreId($model->id),
            new OrganizationId($model->organization_id),
            $model->name,
            $model->code,
            $model->address,
            $model->city,
            $model->state,
            $model->zip_code,
            $model->phone,
            $model->manager_id ? new UserId($model->manager_id) : null,
            $model->active,
            $model->created_at ? new DateTimeImmutable($model->created_at->format('Y-m-d H:i:s')) : null,
            $model->updated_at ? new DateTimeImmutable($model->updated_at->format('Y-m-d H:i:s')) : null
        );
    }

    public static function toEloquent(Store $store): array
    {
        return [
            'id' => $store->getId()->toString(),
            'organization_id' => $store->getOrganizationId()->toString(),
            'manager_id' => $store->getManagerId()?->toString(),
            'name' => $store->getName(),
            'code' => $store->getCode(),
            'address' => $store->getAddress(),
            'city' => $store->getCity(),
            'state' => $store->getState(),
            'zip_code' => $store->getZipCode(),
            'phone' => $store->getPhone(),
            'active' => $store->isActive(),
            'created_at' => $store->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $store->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    public static function toArray(Store $store): array
    {
        return [
            'id' => $store->getId()->toString(),
            'organization_id' => $store->getOrganizationId()->toString(),
            'manager_id' => $store->getManagerId()?->toString(),
            'name' => $store->getName(),
            'code' => $store->getCode(),
            'address' => $store->getAddress(),
            'city' => $store->getCity(),
            'state' => $store->getState(),
            'zip_code' => $store->getZipCode(),
            'phone' => $store->getPhone(),
            'active' => $store->isActive(),
            'has_manager' => $store->hasManager(),
            'created_at' => $store->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $store->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }
}