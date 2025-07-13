<?php

declare(strict_types=1);

namespace App\Application\UseCases\CreateOrganization;

use App\Domain\User\ValueObjects\UserId;

class CreateOrganizationCommand
{
    public function __construct(
        private readonly UserId $requestingUserId,
        private readonly string $name,
        private readonly string $code,
        private readonly ?array $goUserData = null
    ) {}

    public function getRequestingUserId(): UserId
    {
        return $this->requestingUserId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getGoUserData(): ?array
    {
        return $this->goUserData;
    }
}