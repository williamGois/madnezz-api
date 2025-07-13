<?php

declare(strict_types=1);

namespace App\Domain\Organization\Entities;

use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\PositionLevel;
use App\Domain\User\ValueObjects\UserId;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

class Position
{
    private string $id;
    private OrganizationId $organizationId;
    private UserId $userId;
    private PositionLevel $level;
    private string $organizationUnitId;
    private array $departmentIds;
    private bool $active;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        OrganizationId $organizationId,
        UserId $userId,
        PositionLevel $level,
        string $organizationUnitId,
        array $departmentIds,
        bool $active = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->organizationId = $organizationId;
        $this->userId = $userId;
        $this->level = $level;
        $this->organizationUnitId = $organizationUnitId;
        $this->departmentIds = $departmentIds;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public static function create(
        OrganizationId $organizationId,
        UserId $userId,
        PositionLevel $level,
        string $organizationUnitId,
        array $departmentIds = []
    ): self {
        return new self(
            Uuid::uuid4()->toString(),
            $organizationId,
            $userId,
            $level,
            $organizationUnitId,
            $departmentIds,
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

    public function getUserId(): UserId
    {
        return $this->userId;
    }

    public function getLevel(): PositionLevel
    {
        return $this->level;
    }

    public function getOrganizationUnitId(): string
    {
        return $this->organizationUnitId;
    }

    public function getDepartmentIds(): array
    {
        return $this->departmentIds;
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

    public function hasAccessToDepartment(string $departmentId): bool
    {
        return in_array($departmentId, $this->departmentIds, true);
    }

    public function addDepartment(string $departmentId): void
    {
        if (!$this->hasAccessToDepartment($departmentId)) {
            $this->departmentIds[] = $departmentId;
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    public function removeDepartment(string $departmentId): void
    {
        $this->departmentIds = array_filter(
            $this->departmentIds,
            fn($id) => $id !== $departmentId
        );
        $this->updatedAt = new DateTimeImmutable();
    }

    public function updateOrganizationUnit(string $organizationUnitId): void
    {
        $this->organizationUnitId = $organizationUnitId;
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