<?php

declare(strict_types=1);

namespace App\Domain\Organization\Entities;

use App\Domain\Organization\ValueObjects\OrganizationId;
use DateTimeImmutable;

class Organization
{
    private OrganizationId $id;
    private string $name;
    private string $code;
    private bool $active;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        OrganizationId $id,
        string $name,
        string $code,
        bool $active = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->code = $code;
        $this->active = $active;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    public static function create(string $name, string $code): self
    {
        return new self(
            new OrganizationId(),
            $name,
            $code,
            true,
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    public function getId(): OrganizationId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
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