<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Entities;

use App\Domain\Enterprise\ValueObjects\EnterpriseId;
use App\Domain\Enterprise\ValueObjects\EnterpriseName;
use App\Domain\Enterprise\ValueObjects\EnterpriseCode;
use App\Domain\Enterprise\ValueObjects\EnterpriseStatus;
use App\Domain\Organization\ValueObjects\OrganizationId;

class Enterprise
{
    private EnterpriseId $id;
    private EnterpriseName $name;
    private EnterpriseCode $code;
    private OrganizationId $organizationId;
    private EnterpriseStatus $status;
    private ?string $description;
    private ?string $observations;
    private array $metadata;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        EnterpriseId $id,
        EnterpriseName $name,
        EnterpriseCode $code,
        OrganizationId $organizationId,
        EnterpriseStatus $status,
        ?string $description = null,
        ?string $observations = null,
        array $metadata = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->code = $code;
        $this->organizationId = $organizationId;
        $this->status = $status;
        $this->description = $description;
        $this->observations = $observations;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public static function create(
        EnterpriseName $name,
        EnterpriseCode $code,
        OrganizationId $organizationId,
        ?string $description = null,
        ?string $observations = null,
        array $metadata = []
    ): self {
        return new self(
            EnterpriseId::generate(),
            $name,
            $code,
            $organizationId,
            EnterpriseStatus::active(),
            $description,
            $observations,
            $metadata
        );
    }

    public function updateDetails(
        EnterpriseName $name,
        ?string $description = null,
        ?string $observations = null
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->observations = $observations;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateStatus(EnterpriseStatus $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->status = EnterpriseStatus::active();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->status = EnterpriseStatus::inactive();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateMetadata(array $metadata): void
    {
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): EnterpriseId
    {
        return $this->id;
    }

    public function getName(): EnterpriseName
    {
        return $this->name;
    }

    public function getCode(): EnterpriseCode
    {
        return $this->code;
    }

    public function getOrganizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function getStatus(): EnterpriseStatus
    {
        return $this->status;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'name' => $this->name->getValue(),
            'code' => $this->code->getValue(),
            'organization_id' => $this->organizationId->toString(),
            'status' => $this->status->getValue(),
            'description' => $this->description,
            'observations' => $this->observations,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
}