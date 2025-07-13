<?php

declare(strict_types=1);

namespace App\Application\UseCases\CreateStore;

use App\Domain\User\ValueObjects\UserId;
use App\Domain\Organization\ValueObjects\OrganizationId;

class CreateStoreCommand
{
    public function __construct(
        private readonly UserId $requestingUserId,
        private readonly OrganizationId $organizationId,
        private readonly string $name,
        private readonly string $code,
        private readonly string $address,
        private readonly string $city,
        private readonly string $state,
        private readonly string $zipCode,
        private readonly ?string $phone = null,
        private readonly ?array $managerUserData = null
    ) {}

    public function getRequestingUserId(): UserId
    {
        return $this->requestingUserId;
    }

    public function getOrganizationId(): OrganizationId
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

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getZipCode(): string
    {
        return $this->zipCode;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getManagerUserData(): ?array
    {
        return $this->managerUserData;
    }
}