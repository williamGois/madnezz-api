<?php

declare(strict_types=1);

namespace App\Application\Organization\UseCases\CreateRegion;

use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;

class CreateRegionUseCase
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationUnitRepositoryInterface $unitRepository,
        private readonly HierarchicalUserRepositoryInterface $userRepository
    ) {}

    public function execute(CreateRegionCommand $command): CreateRegionResponse
    {
        // Verify that the requesting user is MASTER or GO
        $requestingUser = $this->userRepository->findById($command->getRequestingUserId());
        
        if (!$requestingUser || (!$requestingUser->isMaster() && !$requestingUser->isGO())) {
            throw new \DomainException('Only MASTER or GO users can create regions');
        }

        // Verify organization exists
        $organization = $this->organizationRepository->findById($command->getOrganizationId());
        if (!$organization) {
            throw new \DomainException('Organization not found');
        }

        // If GO, verify they belong to this organization
        if ($requestingUser->isGO() && !$requestingUser->getOrganizationId()->equals($command->getOrganizationId())) {
            throw new \DomainException('GO can only create regions in their own organization');
        }

        // Check if region code already exists in this organization
        $existingUnits = $this->unitRepository->findByOrganization($command->getOrganizationId());
        foreach ($existingUnits as $unit) {
            if ($unit->getCode() === $command->getCode() && $unit->getType() === 'regional') {
                throw new \DomainException("Region with code '{$command->getCode()}' already exists in this organization");
            }
        }

        // Find the company unit (parent for regions)
        $companyUnit = null;
        foreach ($existingUnits as $unit) {
            if ($unit->getType() === 'company') {
                $companyUnit = $unit;
                break;
            }
        }

        if (!$companyUnit) {
            throw new \DomainException('Company unit not found for organization');
        }

        // Create the region
        $region = OrganizationUnit::create(
            $command->getOrganizationId(),
            $command->getName(),
            $command->getCode(),
            'regional',
            $companyUnit->getId()
        );

        // Save the region
        $this->unitRepository->save($region);

        return new CreateRegionResponse(
            $region->getId()->toString(),
            $region->getName(),
            $region->getCode(),
            $command->getOrganizationId()->toString()
        );
    }
}