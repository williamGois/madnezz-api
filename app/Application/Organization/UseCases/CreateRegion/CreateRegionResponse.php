<?php

declare(strict_types=1);

namespace App\Application\Organization\UseCases\CreateRegion;

class CreateRegionResponse
{
    public function __construct(
        public readonly string $regionId,
        public readonly string $name,
        public readonly string $code,
        public readonly string $organizationId
    ) {}
}