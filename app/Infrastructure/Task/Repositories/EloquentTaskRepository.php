<?php

declare(strict_types=1);

namespace App\Infrastructure\Task\Repositories;

use App\Domain\Task\Entities\Task;
use App\Domain\Task\Repositories\TaskRepositoryInterface;
use App\Domain\Task\ValueObjects\TaskId;
use App\Domain\Task\ValueObjects\TaskStatus;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Domain\User\ValueObjects\UserId;
use App\Infrastructure\Task\Eloquent\TaskModel;
use App\Infrastructure\Task\Mappers\TaskMapper;
use Illuminate\Support\Facades\DB;

class EloquentTaskRepository implements TaskRepositoryInterface
{
    public function save(Task $task): void
    {
        $data = TaskMapper::toEloquent($task);
        
        $model = TaskModel::updateOrCreate(
            ['id' => $data['id']],
            $data
        );
        
        // Sync assignees
        $assigneeIds = array_map(fn($userId) => $userId->getValue(), $task->getAssignedUsers());
        $model->assignees()->sync($assigneeIds);
    }
    
    public function findById(TaskId $id): ?Task
    {
        $model = TaskModel::with(['assignees', 'subtasks'])->find($id->getValue());
        
        return $model ? TaskMapper::toDomain($model) : null;
    }
    
    public function findByOrganization(OrganizationId $organizationId): array
    {
        $models = TaskModel::with(['assignees'])
            ->byOrganization($organizationId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
    }
    
    public function findByOrganizationUnit(OrganizationUnitId $unitId): array
    {
        $models = TaskModel::with(['assignees'])
            ->byOrganizationUnit($unitId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
    }
    
    public function findByAssignedUser(UserId $userId): array
    {
        $models = TaskModel::with(['assignees'])
            ->assignedTo($userId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
    }
    
    public function findByCreator(UserId $userId): array
    {
        $models = TaskModel::with(['assignees'])
            ->where('created_by', $userId->getValue())
            ->orderBy('created_at', 'desc')
            ->get();
        
        return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
    }
    
    public function findByStatus(TaskStatus $status, ?OrganizationId $organizationId = null): array
    {
        $query = TaskModel::with(['assignees'])->byStatus($status->getValue());
        
        if ($organizationId) {
            $query->byOrganization($organizationId->getValue());
        }
        
        $models = $query->orderBy('created_at', 'desc')->get();
        
        return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
    }
    
    public function findSubtasks(TaskId $parentId): array
    {
        $models = TaskModel::with(['assignees'])
            ->where('parent_task_id', $parentId->getValue())
            ->orderBy('created_at', 'asc')
            ->get();
        
        return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
    }
    
    public function delete(TaskId $id): void
    {
        TaskModel::destroy($id->getValue());
    }
    
    public function findOverdueTasks(?OrganizationId $organizationId = null): array
    {
        $query = TaskModel::with(['assignees'])->overdue();
        
        if ($organizationId) {
            $query->byOrganization($organizationId->getValue());
        }
        
        $models = $query->orderBy('due_date', 'asc')->get();
        
        return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
    }
    
    public function countByStatus(OrganizationId $organizationId): array
    {
        return TaskModel::byOrganization($organizationId->getValue())
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
    
    public function filterByHierarchy(array $hierarchyFilter): array
    {
        $query = TaskModel::with(['assignees', 'subtasks']);
        
        if (empty($hierarchyFilter['filters'])) {
            // MASTER user - no filters
            $models = $query->orderBy('created_at', 'desc')->get();
        } else {
            $filters = $hierarchyFilter['filters'];
            
            // Apply organization filter
            if (isset($filters['organization_id'])) {
                $query->byOrganization($filters['organization_id']);
            }
            
            // Apply organization unit filter
            if (isset($filters['organization_unit_id'])) {
                if (isset($filters['include_child_units']) && $filters['include_child_units']) {
                    // For GR - include the region and all child units (stores)
                    $unitIds = $filters['all_unit_ids'] ?? [$filters['organization_unit_id']];
                    $query->whereIn('organization_unit_id', $unitIds);
                } else {
                    // For Store Manager - only their specific unit
                    $query->where('organization_unit_id', $filters['organization_unit_id']);
                }
            }
            
            // Apply store filter if present
            if (isset($filters['store_id'])) {
                // Additional filter for store-specific tasks
                $query->where('store_id', $filters['store_id']);
            }
            
            $models = $query->orderBy('created_at', 'desc')->get();
        }
        
        return $models->map(fn($model) => TaskMapper::toDomain($model))->toArray();
    }
}