<?php

declare(strict_types=1);

namespace App\Domain\Organization\Services;

use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\PositionLevel;
use App\Domain\User\ValueObjects\UserId;

class HierarchyPermissionService
{
    public function canUserAccessOrganizationUnit(
        UserId $userId,
        OrganizationId $organizationId,
        string $targetUnitId,
        PositionLevel $userLevel,
        string $userUnitId
    ): bool {
        // GO can access all units in the organization
        if ($userLevel->getValue() === PositionLevel::GO) {
            return true;
        }

        // GR can access their unit and all child units (stores)
        if ($userLevel->getValue() === PositionLevel::GR) {
            return $this->isUnitInHierarchy($userUnitId, $targetUnitId);
        }

        // Store managers can only access their own store
        if ($userLevel->getValue() === PositionLevel::STORE_MANAGER) {
            return $userUnitId === $targetUnitId;
        }

        return false;
    }

    public function canUserManageUser(
        PositionLevel $managerLevel,
        string $managerUnitId,
        PositionLevel $targetUserLevel,
        string $targetUserUnitId
    ): bool {
        // Cannot manage users at higher or same hierarchy level
        if ($targetUserLevel->isHigherThan($managerLevel) || $targetUserLevel->isSameLevel($managerLevel)) {
            return false;
        }

        // GO can manage all GRs and Store Managers
        if ($managerLevel->getValue() === PositionLevel::GO) {
            return true;
        }

        // GR can manage Store Managers in their region
        if ($managerLevel->getValue() === PositionLevel::GR && $targetUserLevel->getValue() === PositionLevel::STORE_MANAGER) {
            return $this->isUnitInHierarchy($managerUnitId, $targetUserUnitId);
        }

        return false;
    }

    public function getUserAccessibleUnits(
        UserId $userId,
        OrganizationId $organizationId,
        PositionLevel $userLevel,
        string $userUnitId
    ): array {
        // GO has access to all units
        if ($userLevel->getValue() === PositionLevel::GO) {
            return $this->getAllOrganizationUnits($organizationId);
        }

        // GR has access to their unit and all child units
        if ($userLevel->getValue() === PositionLevel::GR) {
            return $this->getUnitAndChildren($userUnitId);
        }

        // Store Manager has access only to their unit
        return [$userUnitId];
    }

    private function isUnitInHierarchy(string $parentUnitId, string $targetUnitId): bool
    {
        if ($parentUnitId === $targetUnitId) {
            return true;
        }

        // Check hierarchy using database traversal
        return $this->isChildUnit($parentUnitId, $targetUnitId);
    }

    private function getAllOrganizationUnits(OrganizationId $organizationId): array
    {
        return \DB::table('organization_units')
            ->where('organization_id', $organizationId->getValue())
            ->where('active', true)
            ->pluck('id')
            ->toArray();
    }

    private function getUnitAndChildren(string $unitId): array
    {
        $units = [$unitId];
        $this->collectChildUnits($unitId, $units);
        return $units;
    }

    private function collectChildUnits(string $parentId, array &$units): void
    {
        $children = \DB::table('organization_units')
            ->where('parent_id', $parentId)
            ->where('active', true)
            ->pluck('id')
            ->toArray();

        foreach ($children as $childId) {
            $units[] = $childId;
            $this->collectChildUnits($childId, $units);
        }
    }

    private function isChildUnit(string $parentId, string $childId): bool
    {
        $currentUnit = \DB::table('organization_units')
            ->where('id', $childId)
            ->first();

        while ($currentUnit && $currentUnit->parent_id) {
            if ($currentUnit->parent_id === $parentId) {
                return true;
            }
            
            $currentUnit = \DB::table('organization_units')
                ->where('id', $currentUnit->parent_id)
                ->first();
        }

        return false;
    }
}