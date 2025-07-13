<?php

declare(strict_types=1);

namespace App\Application\UseCases\CreateStore;

use App\Domain\Organization\Entities\Store;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;

class CreateStoreUseCase
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly HierarchicalUserRepositoryInterface $userRepository
    ) {}

    public function execute(CreateStoreCommand $command): CreateStoreResponse
    {
        // Verify that the requesting user can create stores
        $requestingUser = $this->userRepository->findById($command->getRequestingUserId());
        
        if (!$requestingUser) {
            throw new \DomainException('User not found');
        }

        // Only MASTER and GO can create stores
        if (!$requestingUser->isMaster() && !$requestingUser->isGO()) {
            throw new \DomainException('Only MASTER and GO users can create stores');
        }

        // Verify organization exists
        $organization = $this->organizationRepository->findById($command->getOrganizationId());
        if (!$organization) {
            throw new \DomainException('Organization not found');
        }

        // If user is GO, verify they belong to the organization
        if ($requestingUser->isGO() && 
            !$requestingUser->getOrganizationId()?->equals($command->getOrganizationId())) {
            throw new \DomainException('GO users can only create stores in their own organization');
        }

        // Check if store code already exists
        if ($this->storeRepository->codeExists($command->getCode())) {
            throw new \DomainException("Store with code '{$command->getCode()}' already exists");
        }

        // Create the store
        $store = Store::create(
            $command->getOrganizationId(),
            $command->getName(),
            $command->getCode(),
            $command->getAddress(),
            $command->getCity(),
            $command->getState(),
            $command->getZipCode(),
            $command->getPhone()
        );

        // Save the store
        $this->storeRepository->save($store);

        // Create the Store Manager if provided
        $storeManagerUser = null;
        if ($command->getManagerUserData()) {
            $managerData = $command->getManagerUserData();
            
            // Check if email already exists
            if ($this->userRepository->emailExists($managerData['email'])) {
                throw new \DomainException("User with email '{$managerData['email']->getValue()}' already exists");
            }

            $storeManagerUser = HierarchicalUser::createStoreManager(
                $managerData['name'],
                $managerData['email'],
                $managerData['password'],
                $command->getOrganizationId(),
                $store->getId(),
                $managerData['phone'] ?? null
            );

            $this->userRepository->save($storeManagerUser);

            // Assign manager to store
            $store->assignManager($storeManagerUser->getId());
            $this->storeRepository->save($store);
        }

        return new CreateStoreResponse(
            $store->getId()->toString(),
            $store->getName(),
            $store->getCode(),
            $store->getOrganizationId()->toString(),
            $storeManagerUser?->getId()->toString()
        );
    }
}