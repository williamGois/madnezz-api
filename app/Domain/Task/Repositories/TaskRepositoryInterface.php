<?php

declare(strict_types=1);

namespace App\Domain\Task\Repositories;

use App\Domain\Task\Entities\Task;
use App\Domain\Task\ValueObjects\TaskId;
use App\Domain\Task\ValueObjects\TaskStatus;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\User\ValueObjects\UserId;

interface TaskRepositoryInterface
{
    public function save(Task $task): void;
    
    public function findById(TaskId $id): ?Task;
    
    public function findByOrganization(OrganizationId $organizationId): array;
    
    public function findByOrganizationUnit(OrganizationUnitId $unitId): array;
    
    public function findByAssignedUser(UserId $userId): array;
    
    public function findByCreator(UserId $userId): array;
    
    public function findByStatus(TaskStatus $status, ?OrganizationId $organizationId = null): array;
    
    public function findSubtasks(TaskId $parentId): array;
    
    public function delete(TaskId $id): void;
    
    public function findOverdueTasks(?OrganizationId $organizationId = null): array;
    
    public function countByStatus(OrganizationId $organizationId): array;
    
    /**
     * Filter tasks by hierarchy
     * 
     * @param array $hierarchyFilter Contains filters based on user role
     * @return array
     */
    public function filterByHierarchy(array $hierarchyFilter): array;
}