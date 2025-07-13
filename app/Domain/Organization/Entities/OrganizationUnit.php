<?php

declare(strict_types=1);

namespace App\Domain\Organization\Entities;

use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitType;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class OrganizationUnit
{
    private string $id;
    private OrganizationId $organizationId;
    private string $name;
    private string $code;
    private OrganizationUnitType $type;
    private ?string $parentId;
    private bool $active;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        OrganizationId $organizationId,
        string $name,
        string $code,
        OrganizationUnitType $type,
        ?string $parentId = null,
        bool $active = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->name = $name;
        $this->code = $code;
        $this->type = $type;
        $this->parentId = $parentId;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public static function create(
        OrganizationId $organizationId,
        string $name,
        string $code,
        OrganizationUnitType $type,
        ?string $parentId = null
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $organizationId,
            $name,
            $code,
            $type,
            $parentId,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    public function getId(): string
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

    public function getType(): OrganizationUnitType
    {
        return $this->type;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
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

    public function hasParent(): bool
    {
        return $this->parentId !== null;
    }

    public function isChildOf(string $parentId): bool
    {
        return $this->parentId === $parentId;
    }

    public function updateName(string $name): void
    {
        $this->name = $name;
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
}