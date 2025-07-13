<?php

declare(strict_types=1);

namespace App\Application\UseCases\SwitchContext;

use App\Domain\User\Repositories\HierarchicalUserRepositoryInterface;
use App\Domain\Organization\Repositories\OrganizationRepositoryInterface;
use App\Domain\Organization\Repositories\StoreRepositoryInterface;

class SwitchContextUseCase
{
    public function __construct(
        private readonly HierarchicalUserRepositoryInterface $userRepository,
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly StoreRepositoryInterface $storeRepository
    ) {}

    public function execute(SwitchContextCommand $command): SwitchContextResponse
    {
        // Find the MASTER user
        $masterUser = $this->userRepository->findById($command->getMasterUserId());
        
        if (!$masterUser) {
            throw new \DomainException('User not found');
        }

        if (!$masterUser->isMaster()) {
            throw new \DomainException('Only MASTER users can switch context');
        }

        // Validate the target role and context
        $targetRole = $command->getTargetRole();
        $organizationId = $command->getOrganizationId();
        $storeId = $command->getStoreId();

        // Validate organization exists if provided
        if ($organizationId && !$this->organizationRepository->findById($organizationId)) {
            throw new \DomainException('Organization not found');
        }

        // Validate store exists if provided
        if ($storeId && !$this->storeRepository->findById($storeId)) {
            throw new \DomainException('Store not found');
        }

        // Validate role-specific requirements
        match ($targetRole->getValue()) {
            'GO' => $this->validateGOContext($organizationId),
            'GR' => $this->validateGRContext($organizationId),
            'STORE_MANAGER' => $this->validateStoreManagerContext($organizationId, $storeId),
            default => throw new \DomainException('Invalid target role for context switching')
        };

        // Switch the context
        $masterUser->switchContext($targetRole, $organizationId, $storeId);
        
        // Save the updated user
        $this->userRepository->save($masterUser);

        return new SwitchContextResponse(
            $masterUser->getId()->toString(),
            $targetRole->getValue(),
            $organizationId?->toString(),
            $storeId?->toString(),
            $masterUser->getContextData()
        );
    }

    private function validateGOContext(?$organizationId): void
    {
        if (!$organizationId) {
            throw new \DomainException('Organization ID is required for GO context');
        }
    }

    private function validateGRContext(?$organizationId): void
    {
        if (!$organizationId) {
            throw new \DomainException('Organization ID is required for GR context');
        }
    }

    private function validateStoreManagerContext(?$organizationId, ?$storeId): void
    {
        if (!$organizationId) {
            throw new \DomainException('Organization ID is required for Store Manager context');
        }
        
        if (!$storeId) {
            throw new \DomainException('Store ID is required for Store Manager context');
        }

        // Verify store belongs to organization
        $store = $this->storeRepository->findById($storeId);
        if (!$store->getOrganizationId()->equals($organizationId)) {
            throw new \DomainException('Store does not belong to the specified organization');
        }
    }
}