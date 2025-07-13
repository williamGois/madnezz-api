<?php

declare(strict_types=1);

namespace App\Infrastructure\Task\Mappers;

use App\Domain\Task\Entities\Task;
use App\Domain\Task\ValueObjects\TaskId;
use App\Domain\Task\ValueObjects\TaskStatus;
use App\Domain\Task\ValueObjects\TaskPriority;
use App\Domain\User\ValueObjects\UserId;
use App\Domain\Organization\ValueObjects\OrganizationId;
use App\Domain\Organization\ValueObjects\OrganizationUnitId;
use App\Infrastructure\Task\Eloquent\TaskModel;
use DateTime;

class TaskMapper
{
    public static function toDomain(TaskModel $model): Task
    {
        $task = new Task(
            new TaskId($model->id),
            $model->title,
            $model->description,
            new TaskStatus($model->status),
            new TaskPriority($model->priority),
            new UserId($model->created_by),
            new OrganizationId($model->organization_id),
            $model->organization_unit_id ? new OrganizationUnitId($model->organization_unit_id) : null,
            $model->parent_task_id ? new TaskId($model->parent_task_id) : null,
            $model->due_date ? new DateTime($model->due_date) : null,
            new DateTime($model->created_at),
            new DateTime($model->updated_at)
        );
        
        // Add assigned users
        if ($model->assignees) {
            foreach ($model->assignees as $assignee) {
                $task->assignUser(new UserId($assignee->id));
            }
        }
        
        return $task;
    }
    
    public static function toEloquent(Task $task): array
    {
        return [
            'id' => $task->getId()->getValue(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus()->getValue(),
            'priority' => $task->getPriority()->getValue(),
            'created_by' => $task->getCreatedBy()->getValue(),
            'organization_id' => $task->getOrganizationId()->getValue(),
            'organization_unit_id' => $task->getOrganizationUnitId()?->getValue(),
            'parent_task_id' => $task->getParentTaskId()?->getValue(),
            'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
            'completed_at' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $task->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $task->getUpdatedAt()->format('Y-m-d H:i:s')
        ];
    }
}