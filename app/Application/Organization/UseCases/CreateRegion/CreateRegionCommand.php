<?php

declare(strict_types=1);

namespace App\Application\Organization\UseCases\CreateRegion;

use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\User\ValueObjects\UserId;

class CreateRegionCommand
{
    public function __construct(
        private readonly OrganizationId $organizationId,
        private readonly string $name,
        private readonly string $code,
        private readonly UserId $requestingUserId
    ) {}

    public function getOrganizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getRequestingUserId(): UserId
    {
        return $this->requestingUserId;
    }
}