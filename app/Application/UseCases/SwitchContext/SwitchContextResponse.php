<?php

declare(strict_types=1);

namespace App\Application\UseCases\SwitchContext;

class SwitchContextResponse
{
    public function __construct(
        private readonly string $userId,
        private readonly string $currentRole,
        private readonly ?string $organizationId = null,
        private readonly ?string $storeId = null,
        private readonly ?array $contextData = null
    ) {}

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getCurrentRole(): string
    {
        return $this->currentRole;
    }

    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    public function getStoreId(): ?string
    {
        return $this->storeId;
    }

    public function getContextData(): ?array
    {
        return $this->contextData;
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'current_role' => $this->currentRole,
            'organization_id' => $this->organizationId,
            'store_id' => $this->storeId,
            'context_data' => $this->contextData,
        ];
    }
}