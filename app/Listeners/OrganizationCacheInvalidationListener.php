<?php

namespace App\Listeners;

use App\Http\Middleware\OrganizationContextMiddleware;
use Illuminate\Support\Facades\DB;

class OrganizationCacheInvalidationListener
{
    /**
     * Invalidate cache when position changes
     */
    public function handlePositionChange(string $userId): void
    {
        OrganizationContextMiddleware::clearCacheForUser($userId);
    }

    /**
     * Invalidate cache for all users in an organization unit
     */
    public function handleOrganizationUnitChange(string $organizationUnitId): void
    {
        $userIds = DB::table('positions')
            ->where('organization_unit_id', $organizationUnitId)
            ->where('is_active', true)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            OrganizationContextMiddleware::clearCacheForUser($userId);
        }
    }

    /**
     * Invalidate cache for all users in an organization
     */
    public function handleOrganizationChange(string $organizationId): void
    {
        $userIds = DB::table('positions')
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            OrganizationContextMiddleware::clearCacheForUser($userId);
        }
    }

    /**
     * Invalidate cache when department assignment changes
     */
    public function handleDepartmentAssignmentChange(string $positionId): void
    {
        $position = DB::table('positions')
            ->where('id', $positionId)
            ->first();

        if ($position && $position->user_id) {
            OrganizationContextMiddleware::clearCacheForUser($position->user_id);
        }
    }

    /**
     * Invalidate all organization context cache
     */
    public function handleMassInvalidation(): void
    {
        OrganizationContextMiddleware::clearAllCache();
    }
}