<?php

declare(strict_types=1);

namespace App\Domain\Enterprise\Repositories;

use App\Domain\Enterprise\Entities\Enterprise;
use App\Domain\Enterprise\ValueObjects\EnterpriseId;
use App\Domain\Enterprise\ValueObjects\EnterpriseCode;
use App\Domain\Organization\ValueObjects\OrganizationId;

interface EnterpriseRepositoryInterface
{
    public function save(Enterprise $enterprise): void;
    
    public function findById(EnterpriseId $id): ?Enterprise;
    
    public function findByCode(EnterpriseCode $code): ?Enterprise;
    
    public function findByOrganization(OrganizationId $organizationId): array;
    
    public function findAll(array $filters = []): array;
    
    public function exists(EnterpriseId $id): bool;
    
    public function existsByCode(EnterpriseCode $code): bool;
    
    public function delete(EnterpriseId $id): void;
    
    public function countByOrganization(OrganizationId $organizationId): int;
    
    public function findWithStores(EnterpriseId $id): ?array;
    
    public function search(string $query, array $filters = []): array;
}