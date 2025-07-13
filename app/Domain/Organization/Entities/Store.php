<?php

declare(strict_types=1);

namespace App\Domain\Organization\Entities;

use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;

class Store
{
    private StoreId $id;
    private OrganizationId $organizationId;
    private ?UserId $managerId;
    private string $name;
    private string $code;
    private string $address;
    private string $city;
    private string $state;
    private string $zipCode;
    private ?string $phone;
    private bool $active;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        StoreId $id,
        OrganizationId $organizationId,
        string $name,
        string $code,
        string $address,
        string $city,
        string $state,
        string $zipCode,
        ?string $phone = null,
        ?UserId $managerId = null,
        bool $active = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->name = $name;
        $this->code = $code;
        $this->address = $address;
        $this->city = $city;
        $this->state = $state;
        $this->zipCode = $zipCode;
        $this->phone = $phone;
        $this->managerId = $managerId;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public static function create(
        OrganizationId $organizationId,
        string $name,
        string $code,
        string $address,
        string $city,
        string $state,
        string $zipCode,
        ?string $phone = null
    ): self {
        return new self(
            new StoreId(),
            $organizationId,
            $name,
            $code,
            $address,
            $city,
            $state,
            $zipCode,
            $phone,
            null,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    public function getId(): StoreId
    {
        return $this->id;
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

    public function getManagerId(): ?UserId
    {
        return $this->managerId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateAddress(string $address, string $city, string $state, string $zipCode): void
    {
        $this->address = $address;
        $this->city = $city;
        $this->state = $state;
        $this->zipCode = $zipCode;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updatePhone(?string $phone): void
    {
        $this->phone = $phone;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function assignManager(UserId $managerId): void
    {
        $this->managerId = $managerId;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function removeManager(): void
    {
        $this->managerId = null;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->active = true;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->active = false;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function hasManager(): bool
    {
        return $this->managerId !== null;
    }
}