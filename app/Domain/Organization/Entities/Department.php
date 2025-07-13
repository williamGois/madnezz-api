<?php

declare(strict_types=1);

namespace App\Domain\Organization\Entities;

use App\Domain\Organization\ValueObjects\DepartmentType;
use App\Domain\Organization\ValueObjects\OrganizationId;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class Department
{
    private string $id;
    private OrganizationId $organizationId;
    private DepartmentType $type;
    private string $name;
    private string $description;
    private bool $active;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        OrganizationId $organizationId,
        DepartmentType $type,
        string $name,
        string $description,
        bool $active = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->type = $type;
        $this->name = $name;
        $this->description = $description;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public static function create(
        OrganizationId $organizationId,
        DepartmentType $type,
        string $name,
        string $description
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $organizationId,
            $type,
            $name,
            $description,
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

    public function getType(): DepartmentType
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
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

    public function updateDetails(string $name, string $description): void
    {
        $this->name = $name;
        $this->description = $description;
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