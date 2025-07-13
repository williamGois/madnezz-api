<?php

declare(strict_types=1);

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Entities\Position;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\Organization\ValueObjects\PositionId;
use App\Domain\User\ValueObjects\UserId;

interface PositionRepositoryInterface
{
    public function save(Position $position): void;
    
    public function findById(PositionId $id): ?Position;
    
    public function findByUser(UserId $userId): array;
    
    public function findActiveByUser(UserId $userId): ?Position;
    
    public function findByOrganizationUnit(OrganizationUnitId $unitId): array;
    
    public function findByOrganizationUnitString(string $unitId): array;
    
    public function findByOrganization(OrganizationId $organizationId): array;
    
    public function delete(PositionId $id): void;
    
    public function findActiveByUserId(string $userId): ?Position;
}