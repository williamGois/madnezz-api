<?php

declare(strict_types=1);

namespace App\Application\UseCases\CreateStore;

use App\Domain\Organization\Entities\Store;
use App\Domain\Organization\Entities\OrganizationUnit;
use App\Domain\Organization\Entities\Position;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationUnitRepositoryInterface;
use App\Domain\Organization\Repositories\PositionRepositoryInterface;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use App\Domain\User\Entities\HierarchicalUser;
use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;

class CreateStoreUseCase
{
    public function __construct(
        private readonly StoreRepositoryInterface $storeRepository,
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationUnitRepositoryInterface $unitRepository,
        private readonly PositionRepositoryInterface $positionRepository,
        private readonly DepartmentRepositoryInterface $departmentRepository,
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

        // Verify region exists and is of type regional
        $region = $this->unitRepository->findById($command->getRegionId());
        if (!$region || $region->getType() !== 'regional') {
            throw new \DomainException('Region not found');
        }
        
        // Verify region belongs to the organization
        if (!$region->getOrganizationId()->equals($command->getOrganizationId())) {
            throw new \DomainException('Region does not belong to this organization');
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

        // Create store organization unit
        $storeUnit = OrganizationUnit::create(
            $command->getOrganizationId(),
            $command->getName(),
            $command->getCode(),
            'store',
            $command->getRegionId()
        );
        $this->unitRepository->save($storeUnit);

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
            
            // Get administrative department
            $adminDept = $this->departmentRepository->findByCode($command->getOrganizationId(), 'ADM');
            if (!$adminDept) {
                throw new \DomainException('Administrative department not found');
            }

            // Create position linking Store Manager to the store unit
            $position = Position::create(
                $command->getOrganizationId(),
                $storeUnit->getId(),
                $storeManagerUser->getId(),
                $adminDept->getId(),
                'Gerente de Loja',
                'STORE_MANAGER'
            );
            $this->positionRepository->save($position);
        }

        return new CreateStoreResponse(
            $store->getId()->toString(),
            $store->getName(),
            $store->getCode(),
            $store->getOrganizationId()->toString(),
            $storeManagerUser?->getId()->toString(),
            $storeUnit->getId()->toString()
        );
    }
}