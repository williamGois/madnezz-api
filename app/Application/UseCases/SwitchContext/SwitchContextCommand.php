<?php

declare(strict_types=1);

namespace App\Application\UseCases\SwitchContext;

use App\Domain\User\ValueObjects\UserId;
use App\Domain\User\ValueObjects\HierarchyRole;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\StoreId;

class SwitchContextCommand
{
    public function __construct(
        private readonly UserId $masterUserId,
        private readonly HierarchyRole $targetRole,
        private readonly ?OrganizationId $organizationId = null,
        private readonly ?StoreId $storeId = null
    ) {}

    public function getMasterUserId(): UserId
    {
        return $this->masterUserId;
    }

    public function getTargetRole(): HierarchyRole
    {
        return $this->targetRole;
    }

    public function getOrganizationId(): ?OrganizationId
    {
        return $this->organizationId;
    }

    public function getStoreId(): ?StoreId
    {
        return $this->storeId;
    }
}