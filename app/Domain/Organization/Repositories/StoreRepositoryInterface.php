<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;

interface StoreRepositoryInterface
{
    public function save(OrganizationUnit $store): void;
    
    public function findById(OrganizationUnitId $id): ?OrganizationUnit;
    
    public function findByOrganization(OrganizationId $organizationId): array;
    
    public function findByType(string $type): array;
    
    public function findByParent(OrganizationUnitId $parentId): array;
    
    public function delete(OrganizationUnitId $id): void;
}