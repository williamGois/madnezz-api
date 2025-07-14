<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Entities\Organization;
use App\Domain\Organization\ValueObjects\OrganizationId;

interface OrganizationRepositoryInterface
{
    public function save(Organization $organization): void;
    
    public function findById(OrganizationId $id): ?Organization;
    
    public function findByCode(string $code): ?Organization;
    
    public function findActive(): array;
    
    public function delete(OrganizationId $id): void;
    
    public function findAll(): array;
    
    public function codeExists(string $code): bool;
}