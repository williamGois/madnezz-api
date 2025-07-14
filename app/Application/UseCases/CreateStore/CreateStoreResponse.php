<?php

declare(strict_types=1);

namespace App\Application\UseCases\CreateStore;

class CreateStoreResponse
{
    public function __construct(
        private readonly string $storeId,
        private readonly string $name,
        private readonly string $code,
        private readonly string $organizationId,
        private readonly ?string $managerUserId = null,
        private readonly ?string $storeUnitId = null
    ) {}

    public function getStoreId(): string
    {
        return $this->storeId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getManagerUserId(): ?string
    {
        return $this->managerUserId;
    }

    public function getStoreUnitId(): ?string
    {
        return $this->storeUnitId;
    }

    public function toArray(): array
    {
        return [
            'store_id' => $this->storeId,
            'name' => $this->name,
            'code' => $this->code,
            'organization_id' => $this->organizationId,
            'manager_user_id' => $this->managerUserId,
            'store_unit_id' => $this->storeUnitId,
        ];
    }
}