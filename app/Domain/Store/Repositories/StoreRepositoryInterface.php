<?php

declare(strict_types=1);

namespace App\Domain\Store\Repositories;

use App\Domain\Organization\Entities\Store;
use App\Domain\Organization\ValueObjects\StoreId;
use App\Domain\Organization\ValueObjects\OrganizationId;

interface StoreRepositoryInterface
{
    public function save(Store $store): void;
    
    public function findById(StoreId $id): ?Store;
    
    public function findByCode(string $code): ?Store;
    
    public function findByOrganization(OrganizationId $organizationId): array;
    
    public function findActive(): array;
    
    public function codeExists(string $code): bool;
    
    public function delete(StoreId $id): void;
    
    public function findAll(): array;
}