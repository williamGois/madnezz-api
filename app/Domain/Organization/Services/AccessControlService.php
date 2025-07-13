<?php

declare(strict_types=1);

namespace App\Domain\Organization\Services;

use App\Domain\Organization\ValueObjects\DepartmentType;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\PositionLevel;
use App\Domain\User\ValueObjects\UserId;

class AccessControlService
{
    public function __construct(
        private HierarchyPermissionService $hierarchyService
    ) {
    }

    public function canUserAccessDepartment(
        UserId $userId,
        OrganizationId $organizationId,
        DepartmentType $department,
        PositionLevel $userLevel,
        array $userDepartments
    ): bool {
        // Check if user has direct access to the department
        if (in_array($department->getValue(), $userDepartments, true)) {
            return true;
        }

        // GO level users have access to all departments by default
        if ($userLevel->getValue() === PositionLevel::GO) {
            return true;
        }

        return false;
    }

    public function canUserAccessResource(
        UserId $userId,
        OrganizationId $organizationId,
        string $resourceUnitId,
        DepartmentType $resourceDepartment,
        PositionLevel $userLevel,
        string $userUnitId,
        array $userDepartments
    ): bool {
        // First check hierarchy access
        $hasHierarchyAccess = $this->hierarchyService->canUserAccessOrganizationUnit(
            $userId,
            $organizationId,
            $resourceUnitId,
            $userLevel,
            $userUnitId
        );

        if (!$hasHierarchyAccess) {
            return false;
        }

        // Then check department access
        return $this->canUserAccessDepartment(
            $userId,
            $organizationId,
            $resourceDepartment,
            $userLevel,
            $userDepartments
        );
    }

    public function getEffectivePermissions(
        UserId $userId,
        OrganizationId $organizationId,
        PositionLevel $userLevel,
        string $userUnitId,
        array $userDepartments
    ): array {
        $permissions = [];

        // Get accessible units
        $accessibleUnits = $this->hierarchyService->getUserAccessibleUnits(
            $userId,
            $organizationId,
            $userLevel,
            $userUnitId
        );

        // Combine with department access
        foreach ($accessibleUnits as $unitId) {
            foreach ($userDepartments as $department) {
                $permissions[] = [
                    'unit_id' => $unitId,
                    'department' => $department,
                    'level' => $userLevel->getValue(),
                ];
            }
        }

        return $permissions;
    }

    public function validateDelegation(
        PositionLevel $delegatorLevel,
        PositionLevel $delegateeLevel,
        DepartmentType $department
    ): bool {
        // Can only delegate to lower hierarchy levels
        if (!$delegatorLevel->isHigherThan($delegateeLevel)) {
            return false;
        }

        // GO can delegate any department
        if ($delegatorLevel->getValue() === PositionLevel::GO) {
            return true;
        }

        // GR can delegate operational departments to store managers
        if ($delegatorLevel->getValue() === PositionLevel::GR && 
            $delegateeLevel->getValue() === PositionLevel::STORE_MANAGER) {
            $operationalDepartments = [
                DepartmentType::OPERATIONS,
                DepartmentType::TRADE,
                DepartmentType::MARKETING
            ];
            
            return in_array($department->getValue(), array_map(fn($d) => $d, $operationalDepartments), true);
        }

        return false;
    }
}