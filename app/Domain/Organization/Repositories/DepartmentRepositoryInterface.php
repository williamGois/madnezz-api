<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Entities\Department;
use App\Domain\Organization\ValueObjects\DepartmentId;
use App\Domain\Organization\ValueObjects\OrganizationId;

interface DepartmentRepositoryInterface
{
    public function save(Department $department): void;
    
    public function findById(DepartmentId $id): ?Department;
    
    public function findByOrganization(OrganizationId $organizationId): array;
    
    public function findByType(OrganizationId $organizationId, string $type): ?Department;
    
    public function findByCode(OrganizationId $organizationId, string $code): ?Department;
    
    public function delete(DepartmentId $id): void;
}