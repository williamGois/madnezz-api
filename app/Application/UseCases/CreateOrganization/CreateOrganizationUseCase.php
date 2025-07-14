<?php

declare(strict_types=1);

namespace App\Application\UseCases\CreateOrganization;

use App\Domain\Organization\Entities\Organization;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Domain\User\ValueObjects\HierarchyRole;
use App\Http\Middleware\OrganizationContextMiddleware;

class CreateOrganizationUseCase
{
    public function __construct(
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly HierarchicalUserRepositoryInterface $userRepository
    ) {}

    public function execute(CreateOrganizationCommand $command): CreateOrganizationResponse
    {
        // Verify that the requesting user is MASTER
        $requestingUser = $this->userRepository->findById($command->getRequestingUserId());
        
        if (!$requestingUser || !$requestingUser->isMaster()) {
            throw new \DomainException('Only MASTER users can create organizations');
        }

        // Check if organization code already exists
        if ($this->organizationRepository->codeExists($command->getCode())) {
            throw new \DomainException("Organization with code '{$command->getCode()}' already exists");
        }

        // Create the organization
        $organization = Organization::create(
            $command->getName(),
            $command->getCode()
        );

        // Save the organization
        $this->organizationRepository->save($organization);

        // Create the GO user if provided
        $goUser = null;
        if ($command->getGoUserData()) {
            $goData = $command->getGoUserData();
            
            // Check if email already exists
            if ($this->userRepository->emailExists($goData['email'])) {
                throw new \DomainException("User with email '{$goData['email']->getValue()}' already exists");
            }

            $goUser = HierarchicalUser::createGO(
                $goData['name'],
                $goData['email'],
                $goData['password'],
                $organization->getId(),
                $goData['phone'] ?? null
            );

            $this->userRepository->save($goUser);
            
            // Clear cache for the new GO user
            OrganizationContextMiddleware::clearCacheForUser($goUser->getId()->toString());
        }

        return new CreateOrganizationResponse(
            $organization->getId()->toString(),
            $organization->getName(),
            $organization->getCode(),
            $goUser?->getId()->toString()
        );
    }
}