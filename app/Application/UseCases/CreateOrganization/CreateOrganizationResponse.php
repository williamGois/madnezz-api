<?php

declare(strict_types=1);

namespace App\Application\UseCases\CreateOrganization;

class CreateOrganizationResponse
{
    public function __construct(
        private readonly string $organizationId,
        private readonly string $name,
        private readonly string $code,
        private readonly ?string $goUserId = null
    ) {}

    public function getOrganizationId(): string
    {
        return $this->organizationId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getGoUserId(): ?string
    {
        return $this->goUserId;
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'code' => $this->code,
            'go_user_id' => $this->goUserId,
        ];
    }
}