<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;

interface OrganizationUnitRepositoryInterface
{
    public function save(OrganizationUnit $unit): void;
    
    public function findById(OrganizationUnitId $id): ?OrganizationUnit;
    
    public function findByIdString(string $id): ?OrganizationUnit;
    
    public function findByOrganization(OrganizationId $organizationId): array;
    
    public function findChildren(OrganizationUnitId $parentId): array;
    
    public function findByType(OrganizationId $organizationId, string $type): array;
    
    public function delete(OrganizationUnitId $id): void;
}